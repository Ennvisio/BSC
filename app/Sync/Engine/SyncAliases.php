<?php

namespace App\Sync\Engine;

use Illuminate\Support\Facades\DB;

/**
 * Second UUIDs of records that two servers created independently and that
 * were merged into one (see ChangeApplier, 'on_duplicate' => 'merge'). A
 * change or a reference that names the alias reaches the merged record.
 */
class SyncAliases
{
    public function target(string $table, string $uuid): ?string
    {
        return DB::table('sync_aliases')->where('table_name', $table)->where('alias_uuid', strtolower($uuid))->value('target_uuid');
    }

    public function add(string $table, string $alias, string $target, ?string $source): void
    {
        DB::table('sync_aliases')->insertOrIgnore([
            'table_name' => $table,
            'alias_uuid' => strtolower($alias),
            'target_uuid' => strtolower($target),
            'source_node' => $source === null ? null : strtolower($source),
            'created_at' => now(),
        ]);
    }
}
