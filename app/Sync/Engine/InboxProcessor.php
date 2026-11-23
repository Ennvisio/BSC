<?php

namespace App\Sync\Engine;

use App\Sync\Models\SyncConflict;
use App\Sync\Models\SyncInboxEvent;
use App\Sync\SyncInbox;
use App\Sync\SyncRegistry;
use App\Sync\SyncSql;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Receives a list of changes from one sender: stores each in sync_inbox
 * exactly once, then applies it in its own savepoint.
 *
 * One change failing never undoes the others. A change that refers to a row
 * not here yet waits (deferred) and is tried again later. A change that can
 * never be applied is kept as failed, with the reason, for an operator.
 * Only a database-level failure (lost connection, deadlock) aborts the
 * whole list, so the sender sends it again.
 */
class InboxProcessor
{
    use DetectsConcurrencyErrors;
    use DetectsLostConnections;

    public const DUPLICATE = 'duplicate';
    public const DEFERRED = 'deferred';
    public const REJECTED = 'rejected';

    public function __construct(
        private SyncRegistry $registry,
        private SyncInbox $inbox,
        private ChangeApplier $applier,
        private RowCodec $codec,
    ) {
    }

    /**
     * @param  list<mixed>  $events  as received
     * @return list<array{event_id: ?string, status: string, note: ?string, conflict: ?int}>
     */
    public function process(string $source, array $events, ReceivingSide $side, ?string $batchId = null): array
    {
        $this->codec->forgetColumns();
        $results = [];

        foreach ($events as $raw) {
            try {
                $event = self::validate($raw);
            } catch (InvalidChange $e) {
                $results[] = $this->outcome(is_array($raw) && is_string($raw['event_id'] ?? null) ? strtolower($raw['event_id']) : null, self::REJECTED, $e->getMessage());

                continue;
            }

            try {
                $stored = $this->inbox->receive($source, [
                    'event_id' => $event['event_id'],
                    'table_name' => $event['table'],
                    'row_uuid' => $event['row_uuid'],
                    'operation' => $event['op'],
                    'row_version' => $event['version'],
                    'vessel_id' => $event['vessel_id'],
                    'payload' => $event['values'],
                    'actor_uuid' => $event['actor'],
                ]);
            } catch (InvalidArgumentException $e) {
                $results[] = $this->outcome($event['event_id'], self::REJECTED, $e->getMessage());

                continue;
            }

            if (! $stored) {
                $results[] = $this->outcome($event['event_id'], self::DUPLICATE, 'Already received.');

                continue;
            }

            $row = SyncInboxEvent::query()->where('event_id', $event['event_id'])->first();
            if ($batchId !== null) {
                $row->forceFill(['batch_id' => $batchId])->save();
            }

            $results[] = $this->applyStored($row, $side);
        }

        return $results;
    }

    /**
     * Tries again every waiting change from $source, oldest first.
     *
     * @return array{applied: int, deferred: int, failed: int}
     */
    public function retryDeferred(string $source, ReceivingSide $side, bool $includeFailed = false, int $limit = 1000): array
    {
        $counts = ['applied' => 0, 'deferred' => 0, 'failed' => 0];
        $statuses = $includeFailed ? [SyncInboxEvent::STATUS_DEFERRED, SyncInboxEvent::STATUS_FAILED] : [SyncInboxEvent::STATUS_DEFERRED];

        $rows = SyncInboxEvent::query()
            ->where('source_node', strtolower($source))
            ->whereIn('status', $statuses)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            $row->refresh();
            if (! in_array($row->status, $statuses, true)) {
                continue; // superseded meanwhile
            }
            $result = $this->applyStored($row, $side);
            $counts[match ($result['status']) {
                self::DEFERRED => 'deferred',
                self::REJECTED => 'failed',
                default => 'applied',
            }]++;
        }

        return $counts;
    }

    private function applyStored(SyncInboxEvent $row, ReceivingSide $side): array
    {
        $event = [
            'event_id' => $row->event_id,
            'table' => $row->table_name,
            'row_uuid' => $row->row_uuid,
            'op' => $row->operation,
            'version' => $row->row_version,
            'vessel_id' => $row->vessel_id,
            'values' => $row->payload,
            'actor' => $row->actor_uuid,
        ];
        $attempts = $row->attempts + 1;

        try {
            $result = DB::transaction(fn () => $this->applier->apply($event, $row->source_node, $side));
        } catch (MissingReference $e) {
            $max = (int) config('sync.inbox.max_attempts', 50);
            $final = $attempts >= $max;
            $this->update($row, $final ? SyncInboxEvent::STATUS_FAILED : SyncInboxEvent::STATUS_DEFERRED, $attempts,
                $e->getMessage().($final ? " Gave up after {$attempts} attempts." : ''));
            $this->supersedeOlder($row);

            return $this->outcome($row->event_id, $final ? self::REJECTED : self::DEFERRED, $e->getMessage());
        } catch (DuplicateNaturalKey $e) {
            $conflict = $this->recordDuplicate($e);
            $this->update($row, SyncInboxEvent::STATUS_FAILED, $attempts, $e->getMessage());

            return $this->outcome($row->event_id, self::REJECTED, $e->getMessage(), $conflict);
        } catch (InvalidChange $e) {
            $conflict = $this->recordRefusal($row, $side, $e);
            $this->update($row, SyncInboxEvent::STATUS_FAILED, $attempts, $e->getMessage());

            return $this->outcome($row->event_id, self::REJECTED, $e->getMessage(), $conflict);
        } catch (QueryException $e) {
            if ($this->causedByConcurrencyError($e) || $this->causedByLostConnection($e)) {
                throw $e; // the whole batch is retried
            }
            $message = 'Database refused the change: '.$this->shortMessage($e);
            $this->update($row, SyncInboxEvent::STATUS_FAILED, $attempts, $message);

            return $this->outcome($row->event_id, self::REJECTED, $message);
        }

        $applied = in_array($result['status'], [ChangeApplier::APPLIED, ChangeApplier::UNCHANGED, ChangeApplier::TOOK_REMOTE, ChangeApplier::MERGED], true);
        $this->update($row, $applied ? SyncInboxEvent::STATUS_APPLIED : SyncInboxEvent::STATUS_SKIPPED, $attempts, $result['note'], $applied);
        $this->supersedeOlder($row);

        return $this->outcome($row->event_id, $result['status'], $result['note'], $result['conflict']);
    }

    /** An older change to the same row that is still waiting is out of date now. */
    private function supersedeOlder(SyncInboxEvent $row): void
    {
        SyncInboxEvent::query()
            ->where('source_node', $row->source_node)
            ->where('table_name', $row->table_name)
            ->where('row_uuid', $row->row_uuid)
            ->where('id', '<', $row->id)
            ->whereIn('status', [SyncInboxEvent::STATUS_PENDING, SyncInboxEvent::STATUS_DEFERRED])
            ->update(['status' => SyncInboxEvent::STATUS_SKIPPED, 'last_error' => "Superseded by {$row->event_id}."]);
    }

    private function update(SyncInboxEvent $row, string $status, int $attempts, ?string $message, bool $applied = false): void
    {
        $fields = [
            'status' => $status,
            'attempts' => min($attempts, 65535),
            'last_error' => $message === null ? null : mb_substr($message, 0, 2000),
            'applied_at' => $applied ? now() : $row->applied_at,
        ];
        // Done with: sensitive values are not kept (Checkpoint 7). A change
        // that is still waiting or failed keeps them, to be applied later.
        if (in_array($status, [SyncInboxEvent::STATUS_APPLIED, SyncInboxEvent::STATUS_SKIPPED], true)
            && \App\Sync\SensitiveValues::columns($row->table_name) !== [] && is_array($row->payload)) {
            $fields['payload'] = \App\Sync\SensitiveValues::mask($row->table_name, $row->payload);
        }
        $row->forceFill($fields)->save();
    }

    private function recordDuplicate(DuplicateNaturalKey $e): int
    {
        return SyncConflict::create([
            'event_id' => $e->event['event_id'],
            'source_node' => $e->source,
            'detected_by' => $e->side,
            'table_name' => $e->table,
            'row_uuid' => $e->event['row_uuid'],
            'kind' => SyncConflict::KIND_DUPLICATE_KEY,
            'policy' => null,
            'resolution' => SyncConflict::NEEDS_REVIEW,
            'local_version' => $e->existing->sync_version ?? null,
            'remote_version' => $e->event['version'] ?? null,
            'local_values' => (array) $e->existing,
            'remote_values' => $e->event['values'],
            'status' => SyncConflict::STATUS_NEEDS_REVIEW,
            'note' => $e->getMessage(),
            'created_at' => now()->format('Y-m-d H:i:s.u'),
        ])->id;
    }

    /**
     * A change the sender may not make (ownership, a permanent record,
     * ADR-006) is not applied, and a person is told (Checkpoint 14; the
     * conformance review's F3 and F4). Recorded once per event, however
     * often it is retried.
     */
    private function recordRefusal(SyncInboxEvent $row, ReceivingSide $side, InvalidChange $e): int
    {
        $existing = SyncConflict::query()->where('event_id', $row->event_id)->where('kind', SyncConflict::KIND_REFUSED)->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        return SyncConflict::create([
            'event_id' => $row->event_id,
            'source_node' => $row->source_node,
            'detected_by' => $side->name(),
            'table_name' => $row->table_name,
            'row_uuid' => $row->row_uuid,
            'kind' => SyncConflict::KIND_REFUSED,
            'policy' => null,
            'resolution' => SyncConflict::KEPT_LOCAL,
            'local_version' => null,
            'remote_version' => $row->row_version,
            'local_values' => null,
            'remote_values' => is_array($row->payload) ? \App\Sync\SensitiveValues::mask($row->table_name, $row->payload) : null,
            'status' => SyncConflict::STATUS_NEEDS_REVIEW,
            'note' => mb_substr(($row->operation === 'delete' ? 'Delete refused: ' : 'Change refused: ').$e->getMessage(), 0, 2000),
            'created_at' => now()->format('Y-m-d H:i:s.u'),
        ])->id;
    }

    private function outcome(?string $eventId, string $status, ?string $note = null, ?int $conflict = null): array
    {
        return ['event_id' => $eventId, 'status' => $status, 'note' => $note, 'conflict' => $conflict];
    }

    private function shortMessage(Throwable $e): string
    {
        return mb_substr(preg_replace('/\s+\(Connection: .*$/s', '', $e->getMessage()), 0, 500);
    }

    /**
     * Checks the shape of one received event.
     *
     * @return array{event_id: string, table: string, row_uuid: string, op: string, version: ?int, vessel_id: ?int, values: ?array, actor: ?string}
     */
    public static function validate(mixed $raw): array
    {
        $fail = fn (string $m) => throw new InvalidChange('Malformed change: '.$m);

        if (! is_array($raw)) {
            $fail('not an object.');
        }
        if (! SyncSql::isUuid($raw['event_id'] ?? null)) {
            $fail('event_id must be a UUID.');
        }
        if (! is_string($raw['table'] ?? null) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $raw['table'])) {
            $fail('table is missing.');
        }
        if (! SyncSql::isUuid($raw['row_uuid'] ?? null)) {
            $fail('row_uuid must be a UUID.');
        }
        if (! in_array($raw['op'] ?? null, ['insert', 'update', 'delete'], true)) {
            $fail('op must be insert, update or delete.');
        }
        $actor = $raw['actor'] ?? null;
        if ($actor !== null && ! SyncSql::isUuid($actor)) {
            $fail('actor must be a UUID.');
        }
        foreach (['version', 'vessel_id'] as $field) {
            $value = $raw[$field] ?? null;
            if ($value !== null && (! is_int($value) || $value < 1)) {
                $fail("{$field} must be a positive whole number.");
            }
        }
        $values = $raw['values'] ?? null;
        if ($raw['op'] !== 'delete' && ! is_array($values)) {
            $fail('values are required for insert and update.');
        }
        if ($values !== null && (! is_array($values) || array_is_list($values) && $values !== [])) {
            $fail('values must be an object.');
        }

        return [
            'event_id' => strtolower($raw['event_id']),
            'table' => $raw['table'],
            'row_uuid' => strtolower($raw['row_uuid']),
            'op' => $raw['op'],
            'version' => $raw['version'] ?? null,
            'vessel_id' => $raw['vessel_id'] ?? null,
            'values' => $raw['op'] === 'delete' ? null : $values,
            'actor' => $actor === null ? null : strtolower($actor),
        ];
    }
}
