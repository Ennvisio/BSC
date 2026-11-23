<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;

/**
 * A small key/value store in the database (sync_state) for state that must
 * survive a restart: the worker's retry schedule, the cloud's identity as a
 * vessel knows it. Values are stored as JSON.
 */
class SyncState
{
    public function get(string $key, mixed $default = null): mixed
    {
        $value = DB::table('sync_state')->where('key', $key)->value('value');

        return $value === null ? $default : json_decode($value, true);
    }

    public function put(string $key, mixed $value): void
    {
        DB::table('sync_state')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'updated_at' => now()->format('Y-m-d H:i:s.u')]
        );
    }

    public function forget(string $key): void
    {
        DB::table('sync_state')->where('key', $key)->delete();
    }

    /** @return array<string, mixed> every key starting with $prefix */
    public function all(string $prefix = ''): array
    {
        return DB::table('sync_state')
            ->where('key', 'like', $prefix.'%')
            ->orderBy('key')
            ->pluck('value', 'key')
            ->map(fn ($v) => $v === null ? null : json_decode($v, true))
            ->all();
    }
}
