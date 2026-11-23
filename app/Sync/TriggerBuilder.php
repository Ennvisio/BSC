<?php

namespace App\Sync;

/**
 * Generates the change-capture triggers for one registered table.
 *
 * Every insert, update and delete writes one row into sync_outbox in the
 * same transaction as the change itself (a transactional outbox): if the
 * change is rolled back, so is its event. The triggers also maintain the
 * sync columns:
 *
 * - sync_uuid is filled in when a row is inserted without one, and never
 *   changes afterwards.
 * - sync_version starts at 1 and goes up by one on every local update. While
 *   applying a change from another node (an origin is set) the version the
 *   sender gave is kept instead.
 *
 * Each event also records the actor (the user making the change, see
 * SyncContext). A delete additionally writes a lasting row into
 * sync_deletions (Checkpoint 14): the outbox is pruned, the deletion record
 * is not, so a change arriving later can never bring the row back.
 *
 * MySQL is the production engine. SQLite cannot assign NEW values, so its
 * triggers write the generated values back with an UPDATE (recursive
 * triggers are off by default, so that UPDATE does not fire them again).
 */
class TriggerBuilder
{
    public const PREFIX = 'sync_';

    public const OPERATIONS = ['insert', 'update', 'delete'];

    public function __construct(private SyncRegistry $registry)
    {
    }

    /** @return list<string> */
    public function triggerNames(string $driver, string $table): array
    {
        SyncSql::assertSupported($driver);

        $names = [];
        if ($driver === 'mysql') {
            if ($this->registry->hasSyncUuidColumn($table)) {
                $names[] = self::PREFIX.'bi_'.$table;
            }
            $names[] = self::PREFIX.'bu_'.$table;
        }

        foreach (['ai', 'au', 'ad'] as $suffix) {
            $names[] = self::PREFIX.$suffix.'_'.$table;
        }

        return $names;
    }

    /** @return list<string> */
    public function dropStatements(string $driver, string $table): array
    {
        $quote = $driver === 'mysql' ? '`' : '"';

        return array_map(
            fn ($name) => "DROP TRIGGER IF EXISTS {$quote}{$name}{$quote}",
            $this->triggerNames($driver, $table)
        );
    }

    /** @return list<string> */
    public function createStatements(string $driver, string $table): array
    {
        SyncSql::assertSupported($driver);

        return $driver === 'mysql' ? $this->mysql($table) : $this->sqlite($table);
    }

    // ----------------------------------------------------------------- MySQL

    private function mysql(string $table): array
    {
        $uuid = $this->registry->uuidColumn($table);
        $hasSyncUuid = $this->registry->hasSyncUuidColumn($table);
        $statements = [];

        if ($hasSyncUuid) {
            $statements[] = <<<SQL
                CREATE TRIGGER `sync_bi_{$table}` BEFORE INSERT ON `{$table}` FOR EACH ROW
                BEGIN
                    IF NEW.`sync_uuid` IS NULL OR NEW.`sync_uuid` = '' THEN
                        SET NEW.`sync_uuid` = UUID();
                    END IF;
                END
                SQL;
        }

        $keepUuid = $hasSyncUuid
            ? "SET NEW.`sync_uuid` = COALESCE(OLD.`sync_uuid`, NULLIF(NEW.`sync_uuid`, ''), UUID());"
            : '';

        $statements[] = <<<SQL
            CREATE TRIGGER `sync_bu_{$table}` BEFORE UPDATE ON `{$table}` FOR EACH ROW
            BEGIN
                {$keepUuid}
                IF @sync_origin IS NULL AND @sync_capture_off IS NULL THEN
                    SET NEW.`sync_version` = OLD.`sync_version` + 1;
                END IF;
            END
            SQL;

        foreach (['insert' => ['ai', 'INSERT', 'NEW'], 'update' => ['au', 'UPDATE', 'NEW'], 'delete' => ['ad', 'DELETE', 'OLD']] as $operation => [$suffix, $event, $row]) {
            $vessel = $this->registry->vesselExpression($table, $row);

            $tombstone = $operation === 'delete' ? <<<SQL
                INSERT INTO `sync_deletions`
                            (`table_name`, `row_id`, `row_uuid`, `vessel_id`, `actor_uuid`, `origin_node`, `deleted_at`)
                        VALUES
                            ('{$table}', OLD.`id`, OLD.`{$uuid}`, {$vessel}, @sync_actor, @sync_origin, CURRENT_TIMESTAMP(6));
                SQL : '';

            $statements[] = <<<SQL
                CREATE TRIGGER `sync_{$suffix}_{$table}` AFTER {$event} ON `{$table}` FOR EACH ROW
                BEGIN
                    IF @sync_capture_off IS NULL THEN
                        INSERT INTO `sync_outbox`
                            (`event_id`, `table_name`, `row_id`, `row_uuid`, `operation`, `row_version`, `vessel_id`, `origin_node`, `actor_uuid`, `created_at`)
                        VALUES
                            (UUID(), '{$table}', {$row}.`id`, {$row}.`{$uuid}`, '{$operation}', {$row}.`sync_version`, {$vessel}, @sync_origin, @sync_actor, CURRENT_TIMESTAMP(6));
                        {$tombstone}
                    END IF;
                END
                SQL;
        }

        return $statements;
    }

    // ---------------------------------------------------------------- SQLite

    private function sqlite(string $table): array
    {
        $uuid = $this->registry->uuidColumn($table);
        $hasSyncUuid = $this->registry->hasSyncUuidColumn($table);
        $newUuid = SyncSql::uuidExpression('sqlite');
        $now = SyncSql::nowExpression('sqlite');
        $columns = '("event_id", "table_name", "row_id", "row_uuid", "operation", "row_version", "vessel_id", "origin_node", "actor_uuid", "created_at")';
        $currentUuid = "(SELECT \"{$uuid}\" FROM \"{$table}\" WHERE \"id\" = NEW.\"id\")";

        $fillUuid = $hasSyncUuid
            ? "UPDATE \"{$table}\" SET \"sync_uuid\" = {$newUuid} WHERE \"id\" = NEW.\"id\" AND (\"sync_uuid\" IS NULL OR \"sync_uuid\" = '');"
            : '';

        $statements = [];

        $statements[] = <<<SQL
            CREATE TRIGGER "sync_ai_{$table}" AFTER INSERT ON "{$table}" FOR EACH ROW
            BEGIN
                {$fillUuid}
                INSERT INTO "sync_outbox" {$columns}
                SELECT {$newUuid}, '{$table}', NEW."id", {$currentUuid}, 'insert', NEW."sync_version",
                       {$this->registry->vesselExpression($table, 'NEW')}, sync_origin(), sync_actor(), {$now}
                WHERE sync_capture_enabled();
            END
            SQL;

        // The insert trigger's own UPDATE (filling in sync_uuid) is not a
        // change anyone made, so it is skipped here.
        $when = $hasSyncUuid ? 'WHEN NOT (OLD."sync_uuid" IS NULL AND NEW."sync_uuid" IS NOT NULL)' : '';
        $keepUuid = $hasSyncUuid
            ? "\"sync_uuid\" = COALESCE(OLD.\"sync_uuid\", NULLIF(NEW.\"sync_uuid\", ''), {$newUuid}),"
            : '';

        $statements[] = <<<SQL
            CREATE TRIGGER "sync_au_{$table}" AFTER UPDATE ON "{$table}" FOR EACH ROW
            {$when}
            BEGIN
                UPDATE "{$table}" SET {$keepUuid}
                    "sync_version" = CASE WHEN sync_origin() IS NULL AND sync_capture_enabled() THEN OLD."sync_version" + 1 ELSE NEW."sync_version" END
                WHERE "id" = NEW."id";
                INSERT INTO "sync_outbox" {$columns}
                SELECT {$newUuid}, '{$table}', NEW."id", {$currentUuid}, 'update',
                       (SELECT "sync_version" FROM "{$table}" WHERE "id" = NEW."id"),
                       {$this->registry->vesselExpression($table, 'NEW')}, sync_origin(), sync_actor(), {$now}
                WHERE sync_capture_enabled();
            END
            SQL;

        $statements[] = <<<SQL
            CREATE TRIGGER "sync_ad_{$table}" AFTER DELETE ON "{$table}" FOR EACH ROW
            BEGIN
                INSERT INTO "sync_outbox" {$columns}
                SELECT {$newUuid}, '{$table}', OLD."id", OLD."{$uuid}", 'delete', OLD."sync_version",
                       {$this->registry->vesselExpression($table, 'OLD')}, sync_origin(), sync_actor(), {$now}
                WHERE sync_capture_enabled();
                INSERT INTO "sync_deletions" ("table_name", "row_id", "row_uuid", "vessel_id", "actor_uuid", "origin_node", "deleted_at")
                SELECT '{$table}', OLD."id", OLD."{$uuid}", {$this->registry->vesselExpression($table, 'OLD')}, sync_actor(), sync_origin(), {$now}
                WHERE sync_capture_enabled();
            END
            SQL;

        return $statements;
    }
}
