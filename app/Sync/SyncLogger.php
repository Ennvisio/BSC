<?php

namespace App\Sync;

use App\Sync\Models\SyncLog;

/** Writes one sync_logs row per sync action, for the operator screens. */
class SyncLogger
{
    public function record(string $action, string $status, array $details = []): SyncLog
    {
        return SyncLog::create([
            'node_id' => $details['node_id'] ?? null,
            'action' => $action,
            'status' => $status,
            'events' => $details['events'] ?? 0,
            'bytes' => $details['bytes'] ?? 0,
            'duration_ms' => $details['duration_ms'] ?? null,
            'message' => $details['message'] ?? null,
            'context' => $details['context'] ?? null,
            'created_at' => now()->format('Y-m-d H:i:s.u'),
        ]);
    }
}
