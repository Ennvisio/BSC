<?php

namespace App\Sync;

use App\Sync\Models\SyncInboxEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stores changes received from another node, exactly once.
 *
 * sync_inbox.event_id is unique in the database, so a retried or repeated
 * delivery of the same event is recognised and ignored, even if two
 * deliveries race. Applying the stored events to the application tables is
 * a later checkpoint.
 */
class SyncInbox
{
    public function __construct(private SyncRegistry $registry)
    {
    }

    /**
     * @param  array{event_id: string, table_name: string, row_uuid: string, operation: string, row_version?: ?int, vessel_id?: ?int, payload?: ?array}  $event
     * @return bool true when stored, false when this event was already received
     */
    public function receive(string $sourceNode, array $event): bool
    {
        $row = $this->validated($sourceNode, $event);

        try {
            // A savepoint inside any surrounding transaction, so a duplicate
            // never poisons the caller's transaction on stricter databases.
            DB::transaction(fn () => SyncInboxEvent::query()->insert($row));

            return true;
        } catch (UniqueConstraintViolationException $e) {
            if (SyncInboxEvent::query()->where('event_id', $row['event_id'])->exists()) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Stores a batch atomically. The whole batch is checked first, so one
     * malformed event rejects the batch before anything is stored.
     *
     * @param  list<array>  $events
     * @return array{accepted: list<string>, duplicates: list<string>}
     */
    public function receiveBatch(string $sourceNode, array $events): array
    {
        foreach ($events as $event) {
            $this->validated($sourceNode, $event);
        }

        return DB::transaction(function () use ($sourceNode, $events) {
            $result = ['accepted' => [], 'duplicates' => []];

            foreach ($events as $event) {
                $key = $this->receive($sourceNode, $event) ? 'accepted' : 'duplicates';
                $result[$key][] = strtolower($event['event_id']);
            }

            return $result;
        });
    }

    private function validated(string $sourceNode, array $event): array
    {
        $fail = fn (string $message) => throw new InvalidArgumentException('Rejected sync event: '.$message);

        if (! SyncSql::isUuid($sourceNode)) {
            $fail('the source node is not a UUID.');
        }
        if (! SyncSql::isUuid($event['event_id'] ?? null)) {
            $fail('event_id is missing or not a UUID.');
        }
        if (! is_string($event['table_name'] ?? null) || ! $this->registry->isSynced($event['table_name'])) {
            $fail('table_name is not a synchronised table.');
        }
        if (! SyncSql::isUuid($event['row_uuid'] ?? null)) {
            $fail('row_uuid is missing or not a UUID.');
        }
        if (! in_array($event['operation'] ?? null, TriggerBuilder::OPERATIONS, true)) {
            $fail('operation must be insert, update or delete.');
        }

        $version = $event['row_version'] ?? null;
        if ($version !== null && (! is_int($version) || $version < 1)) {
            $fail('row_version must be a positive whole number.');
        }

        $vessel = $event['vessel_id'] ?? null;
        if ($vessel !== null && (! is_int($vessel) || $vessel < 1)) {
            $fail('vessel_id must be a positive whole number.');
        }

        $payload = $event['payload'] ?? null;
        if ($payload !== null && ! is_array($payload)) {
            $fail('payload must be an object.');
        }

        // Checkpoint 14: the user who made the change on the sender.
        $actor = $event['actor_uuid'] ?? null;
        if ($actor !== null && ! SyncSql::isUuid($actor)) {
            $fail('actor_uuid must be a UUID.');
        }

        return [
            'event_id' => strtolower($event['event_id']),
            'source_node' => strtolower($sourceNode),
            'table_name' => $event['table_name'],
            'row_uuid' => strtolower($event['row_uuid']),
            'operation' => $event['operation'],
            'row_version' => $version,
            'vessel_id' => $vessel,
            'payload' => $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'actor_uuid' => $actor === null ? null : strtolower($actor),
            'status' => SyncInboxEvent::STATUS_PENDING,
            'attempts' => 0,
            'received_at' => now()->format('Y-m-d H:i:s.u'),
        ];
    }
}
