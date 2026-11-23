<?php

namespace App\Sync\Engine;

use App\Sync\Files\FileColumns;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the events one server sends to another from its outbox.
 *
 * The outbox records that a row changed, not how. So each event carries the
 * row's state at the moment it is packaged, and several changes to the same
 * row in one page become a single event: the row's current values, at the
 * position of its first change, so a parent created before its child still
 * arrives first. A row that no longer exists is sent as a delete.
 */
class ChangePackager
{
    public function __construct(private RowCodec $codec, private ReplicationRules $rules, private RowScope $scope)
    {
    }

    /**
     * The events after position $after, up to $highWater, that $scope lets
     * through.
     *
     * $forVessel is the vessel receiving the page (the cloud's pull): a row
     * that does not belong to it is sent with the table's redactions (a
     * shore user's password hash never leaves the office).
     *
     * @param  Closure(Builder): void  $scope  narrows the outbox query
     * @return array{events: list<array>, cursor: int, has_more: bool, scanned: int, bytes: int, dangling: list<string>}
     */
    public function page(int $after, int $highWater, Closure $scope, int $limit, int $maxBytes, ?int $forVessel = null): array
    {
        $this->codec->danglingReferences = [];

        $raw = DB::table('sync_outbox')
            ->where('seq', '>', $after)
            ->where('seq', '<=', $highWater)
            ->tap($scope)
            ->orderBy('seq')
            ->limit($limit)
            ->get();

        $hasMore = $raw->count() === $limit;
        // Nothing else in range matched: the reader is up to date to $highWater.
        $cursor = $hasMore ? (int) $raw->last()->seq : max($after, $highWater);

        $groups = [];
        foreach ($raw as $event) {
            if ($event->row_uuid === null) {
                continue;
            }
            $key = $event->table_name.'|'.strtolower($event->row_uuid);
            if (! isset($groups[$key])) {
                $groups[$key] = ['first' => $event, 'last' => $event];
            } else {
                $groups[$key]['last'] = $event;
            }
        }

        $byTable = [];
        foreach ($groups as $group) {
            $byTable[$group['first']->table_name][] = strtolower($group['first']->row_uuid);
        }
        $current = [];
        foreach ($byTable as $table => $uuids) {
            $current[$table] = $this->codec->encodeCurrent($table, $uuids);
        }

        $events = [];
        $sent = [];
        foreach ($groups as $group) {
            $first = $group['first'];
            $last = $group['last'];
            $uuid = strtolower($first->row_uuid);
            $row = $current[$first->table_name][$uuid] ?? null;

            $values = $row['values'] ?? null;
            if ($values !== null && $forVessel !== null && ($redact = $this->rules->redactions($first->table_name)) !== []
                && $this->scope->vesselOf($first->table_name, $last->row_id) !== $forVessel) {
                $values = array_replace($values, array_intersect_key($redact, $values));
            }

            if (isset($sent[$first->table_name.'|'.$uuid])) {
                continue; // already carried earlier in this page
            }
            if ($forVessel !== null && $this->rules->strictScope($first->table_name)
                && ! $this->belongsTo($first->table_name, $last->row_id, $uuid, $forVessel)) {
                continue; // belongs to no vessel, or another: sent only when carried (below)
            }

            if ($forVessel !== null && $values !== null) {
                foreach ($this->rules->carries($first->table_name) as $column => $carried) {
                    $carriedUuid = is_string($values[$column] ?? null) ? strtolower($values[$column]) : null;
                    if ($carriedUuid !== null && ! isset($sent[$carried.'|'.$carriedUuid])
                        && ($event = $this->carriedEvent($carried, $carriedUuid, (int) $last->seq, $forVessel)) !== null) {
                        $events[] = $event;
                        $sent[$carried.'|'.$carriedUuid] = true;
                    }
                }
            }

            if ($forVessel !== null && $values !== null && FileColumns::forTable($first->table_name) !== []) {
                $this->grant($forVessel, $first->table_name, $uuid);
            }
            $sent[$first->table_name.'|'.$uuid] = true;

            $events[] = [
                'event_id' => strtolower($last->event_id),
                'seq' => (int) $last->seq,
                'table' => $first->table_name,
                'row_uuid' => $uuid,
                'op' => $row === null ? 'delete' : ($first->operation === 'insert' ? 'insert' : 'update'),
                'version' => $row['version'] ?? ($last->row_version === null ? null : (int) $last->row_version),
                'vessel_id' => $last->vessel_id === null ? null : (int) $last->vessel_id,
                'values' => $values,
                // Checkpoint 14: who made the latest change (null = the system).
                'actor' => isset($last->actor_uuid) ? strtolower($last->actor_uuid) : null,
            ];
        }

        $bytes = strlen(json_encode($events, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if ($bytes > $maxBytes && $limit > 1) {
            return $this->page($after, $highWater, $scope, intdiv($limit, 2), $maxBytes, $forVessel);
        }

        return [
            'events' => $events,
            'cursor' => $cursor,
            'has_more' => $hasMore,
            'scanned' => $raw->count(),
            'bytes' => $bytes,
            'dangling' => array_values(array_unique($this->codec->danglingReferences)),
        ];
    }

    /** The row belongs to the vessel, or was carried to it before. */
    private function belongsTo(string $table, int|string $rowId, string $uuid, int $vesselId): bool
    {
        if ($this->scope->vesselOf($table, $rowId) === $vesselId) {
            return true;
        }

        return DB::table('sync_file_grants')->where(['vessel_id' => $vesselId, 'table_name' => $table, 'row_uuid' => $uuid])->exists();
    }

    /**
     * A row sent along with a link to it (an office attachment linked to a
     * vessel's procurement stage), placed before the link so it arrives
     * first. Its event id is derived from the row and version, so sending
     * it again is recognised as a duplicate.
     */
    private function carriedEvent(string $table, string $uuid, int $seq, int $vesselId): ?array
    {
        $row = $this->codec->encodeCurrent($table, [$uuid])[$uuid] ?? null;
        if ($row === null) {
            return null;
        }
        $this->grant($vesselId, $table, $uuid);

        $hash = sha1("carried|{$table}|{$uuid}|{$row['version']}|{$vesselId}");
        $eventId = sprintf('%s-%s-5%s-%s%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 13, 3),
            dechex(8 | (hexdec($hash[16]) & 3)), substr($hash, 17, 3), substr($hash, 20, 12));

        return [
            'event_id' => $eventId,
            'seq' => $seq,
            'table' => $table,
            'row_uuid' => $uuid,
            'op' => 'update',
            'version' => $row['version'],
            'vessel_id' => $vesselId,
            'values' => $row['values'],
        ];
    }

    /** The vessel may download the files of a row the cloud sent it. */
    private function grant(int $vesselId, string $table, string $uuid): void
    {
        DB::table('sync_file_grants')->insertOrIgnore(['vessel_id' => $vesselId, 'table_name' => $table, 'row_uuid' => $uuid, 'created_at' => now()]);
    }
}
