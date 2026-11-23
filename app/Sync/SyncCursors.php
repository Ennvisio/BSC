<?php

namespace App\Sync;

use App\Sync\Models\SyncCursor;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

/**
 * Remembers how far each peer node has got through a stream of events, for
 * example the highest cloud outbox id a vessel has acknowledged. A cursor
 * only ever moves forward, so a late or repeated acknowledgement can never
 * make a node re-send or skip events.
 */
class SyncCursors
{
    public function position(string $nodeId, string $stream): int
    {
        return (int) SyncCursor::query()
            ->where('node_id', strtolower($nodeId))
            ->where('stream', $stream)
            ->value('position');
    }

    /** @return bool whether the cursor moved */
    public function advance(string $nodeId, string $stream, int $position, ?string $lastEventId = null): bool
    {
        if (! SyncSql::isUuid($nodeId) || $stream === '' || $position < 0) {
            throw new InvalidArgumentException('A cursor needs a node UUID, a stream name and a non-negative position.');
        }

        $nodeId = strtolower($nodeId);
        $values = ['position' => $position, 'last_event_id' => $lastEventId, 'updated_at' => now()];

        $moved = SyncCursor::query()
            ->where('node_id', $nodeId)
            ->where('stream', $stream)
            ->where('position', '<', $position)
            ->update($values);

        if ($moved > 0) {
            return true;
        }

        if (SyncCursor::query()->where('node_id', $nodeId)->where('stream', $stream)->exists()) {
            return false; // already at or past $position
        }

        try {
            SyncCursor::query()->insert($values + ['node_id' => $nodeId, 'stream' => $stream, 'created_at' => now()]);

            return true;
        } catch (UniqueConstraintViolationException) {
            // Another process created it first: try once more to move it.
            return $this->advance($nodeId, $stream, $position, $lastEventId);
        }
    }
}
