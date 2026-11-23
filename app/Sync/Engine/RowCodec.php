<?php

namespace App\Sync\Engine;

use App\Sync\SyncRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns table rows into the values sent between servers, and back.
 *
 * Local ids differ between servers (each assigns its own), so a foreign key
 * travels as the UUID of the row it points to and is turned back into that
 * row's local id on arrival. The row's own id, sync_uuid and sync_version
 * never travel inside the values: the UUID and version are sent alongside.
 */
class RowCodec
{
    /** @var array<string, list<string>> */
    private array $columns = [];

    /** Dangling references met while encoding, for the log: "table.column=id". */
    public array $danglingReferences = [];

    public function __construct(private SyncRegistry $registry, private ReplicationRules $rules, private SyncAliases $aliases)
    {
    }

    /** @var array<string, list<string>> */
    private array $generated = [];

    /** @return list<string> */
    public function columns(string $table): array
    {
        return $this->columns[$table] ??= Schema::getColumnListing($table);
    }

    /**
     * Columns the database computes itself (pms_components.unique_scope):
     * never sent, never written.
     *
     * @return list<string>
     */
    public function generatedColumns(string $table): array
    {
        return $this->generated[$table] ??= array_values(array_map(
            fn ($c) => $c['name'],
            array_filter(Schema::getColumns($table), fn ($c) => ! empty($c['generation'])),
        ));
    }

    public function forgetColumns(): void
    {
        $this->columns = [];
        $this->generated = [];
    }

    /**
     * Current rows of $table by UUID, encoded for sending.
     *
     * @param  list<string>  $uuids
     * @return array<string, array{version: int, values: array<string, mixed>}> keyed by UUID
     */
    public function encodeCurrent(string $table, array $uuids): array
    {
        if ($uuids === []) {
            return [];
        }

        $uuidColumn = $this->registry->uuidColumn($table);
        $rows = DB::table($table)->whereIn($uuidColumn, $uuids)->get();
        $unsent = [...$this->rules->unsentColumns($table), ...$this->generatedColumns($table)];
        $references = $this->references($table);

        // One lookup per referenced table for the whole page.
        $wanted = [];
        foreach ($references as $column => $referenced) {
            foreach ($rows as $row) {
                if ($row->{$column} !== null) {
                    $wanted[$referenced][] = $row->{$column};
                }
            }
        }
        $uuidOf = [];
        foreach ($wanted as $referenced => $ids) {
            $refUuid = $this->registry->uuidColumn($referenced);
            $uuidOf[$referenced] = DB::table($referenced)->whereIn('id', array_values(array_unique($ids)))->pluck($refUuid, 'id')->all();
        }

        $encoded = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ((array) $row as $column => $value) {
                if (in_array($column, $unsent, true)) {
                    continue;
                }
                if (isset($references[$column]) && $value !== null) {
                    $found = $uuidOf[$references[$column]][$value] ?? null;
                    if ($found === null) {
                        // The row points at something that does not exist
                        // (old data without foreign keys, or a name where an
                        // id belongs). Its id would mean nothing elsewhere,
                        // so the column is left out: the receiver keeps what
                        // it has rather than having it blanked.
                        $this->danglingReferences[] = "{$table}.{$column}={$value}";

                        continue;
                    }
                    $value = $found;
                }
                $values[$column] = $value;
            }
            $encoded[strtolower($row->{$uuidColumn})] = ['version' => (int) $row->sync_version, 'values' => $values];
        }

        return $encoded;
    }

    /**
     * Received values as local column values: only columns this table has
     * here and that may be written, with references turned into local ids.
     *
     * @throws MissingReference when a referenced row is not here (yet)
     * @return array{values: array<string, mixed>, ignored: list<string>}
     */
    public function decode(string $table, array $values): array
    {
        $local = $this->columns($table);
        $unsent = [...$this->rules->unsentColumns($table), ...$this->generatedColumns($table)];
        $references = $this->references($table);
        $out = [];
        $ignored = [];

        foreach ($values as $column => $value) {
            if (! is_string($column) || ! in_array($column, $local, true) || in_array($column, $unsent, true)) {
                $ignored[] = (string) $column;

                continue;
            }
            if (is_array($value) || is_object($value)) {
                throw new InvalidChange("Value of {$column} must be a scalar.");
            }
            if (isset($references[$column]) && $value !== null) {
                $value = $this->localId($references[$column], (string) $value, $column);
            }
            $out[$column] = $value;
        }

        return ['values' => $out, 'ignored' => $ignored];
    }

    /** Whether the local row already holds exactly these values. */
    public function same(object $localRow, array $values): bool
    {
        foreach ($values as $column => $value) {
            if (! property_exists($localRow, $column) || $this->normalise($localRow->{$column}) !== $this->normalise($value)) {
                return false;
            }
        }

        return true;
    }

    /** The values of a local row as they would be sent, for conflict records. */
    public function snapshot(string $table, ?object $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $uuid = $row->{$this->registry->uuidColumn($table)};

        return $this->encodeCurrent($table, [$uuid])[strtolower($uuid)]['values'] ?? null;
    }

    /** @return array<string, string> */
    private function references(string $table): array
    {
        return $this->rules->references($table);
    }

    private function localId(string $table, string $uuid, string $column): mixed
    {
        $uuidColumn = $this->registry->uuidColumn($table);
        $uuid = strtolower($uuid);
        // A record created on both sides and merged answers to both UUIDs.
        $candidates = array_values(array_filter([$uuid, $this->aliases->target($table, $uuid)]));

        foreach ($candidates as $candidate) {
            if ($uuidColumn === 'id') {
                // UUID primary key: the reference is the UUID itself.
                if (DB::table($table)->where('id', $candidate)->exists()) {
                    return $candidate;
                }

                continue;
            }

            $id = DB::table($table)->where($uuidColumn, $candidate)->value('id');
            if ($id !== null) {
                return $id;
            }
        }

        throw new MissingReference($table, $column, $uuid);
    }

    private function normalise(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
