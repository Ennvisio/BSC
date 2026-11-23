<?php

namespace App\Sync\Engine;

use App\Sync\SyncRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Which vessel a row belongs to, worked out with the same SQL the capture
 * triggers use (config/sync.php 'vessel'), so the answer always agrees with
 * how the row's changes are routed.
 */
class RowScope
{
    public function __construct(private SyncRegistry $registry)
    {
    }

    public function isVesselScoped(string $table): bool
    {
        return $this->registry->definition($table)['vessel'] !== null;
    }

    public function vesselOf(string $table, int|string $id): ?int
    {
        $expression = $this->registry->definition($table)['vessel'];
        if ($expression === null) {
            return null;
        }

        $sql = str_replace('{row}', 'scope_row', $expression);
        $value = DB::selectOne("SELECT ({$sql}) AS vessel FROM {$this->quote($table)} scope_row WHERE scope_row.id = ?", [$id])?->vessel;

        return $value === null ? null : (int) $value;
    }

    private function quote(string $table): string
    {
        return DB::connection()->getDriverName() === 'mysql' ? "`{$table}`" : "\"{$table}\"";
    }
}
