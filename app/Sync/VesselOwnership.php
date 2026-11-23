<?php

namespace App\Sync;

use App\Support\AppMode;
use App\Sync\Engine\ReplicationRules;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Which rows are a vessel's own records (PD-34, decided 2026-11-23 and
 * confirmed 2026-11-24): the cloud must not delete them, and the vessel
 * refuses such a delete if one ever arrives.
 *
 * One rule, used on both sides, so they never disagree:
 *  - the vessel's sync refusal (ChangeApplier::delete);
 *  - the cloud's guard against office deletes (cloudDeleteRefusal(), called
 *    for every Eloquent delete by SyncServiceProvider, and by the
 *    few query-builder deletes that can reach a vessel's rows).
 *
 * A row is the vessel's own record when its table is a vessel-owned
 * operational table (class C), only the vessel creates rows in it
 * (requisitions, their lines and approvals, PMS jobs), the vessel keeps
 * that particular row in a shared table (requisition form Part A, Receipt
 * & Verification), or it is a file uploaded on board. Records both sides
 * edit (certificates and surveys, PD-17) and shared master data are not.
 */
class VesselOwnership
{
    public function __construct(private SyncRegistry $registry, private ReplicationRules $rules)
    {
    }

    /**
     * Why a row of $table is its vessel's own record, or null when it is not.
     * $vesselId is the row's vessel, when known (a file only counts when it
     * was uploaded by a vessel's crew).
     */
    public function reason(string $table, array $row, ?int $vesselId): ?string
    {
        if (! $this->registry->isSynced($table)) {
            return null;
        }
        $class = $this->registry->definition($table)['class'];

        return match (true) {
            $class === 'C' => 'a vessel-owned operational record',
            $this->rules->creator($table) === ReplicationRules::VESSEL => 'raised on board',
            $this->rules->rowOwner($table, $row) === ReplicationRules::VESSEL => 'kept by the vessel',
            $class === 'F' && $vesselId !== null => 'uploaded on board',
            default => null,
        };
    }

    /** The vessel a stored row belongs to, from the table's sync definition. Null for fleet-wide rows. */
    public function vesselOf(string $table, string $keyColumn, mixed $key): ?int
    {
        if (! $this->registry->isSynced($table) || $key === null) {
            return null;
        }
        $expression = $this->registry->definition($table)['vessel'];
        if ($expression === null) {
            return null;
        }

        $vessel = DB::table($table)->where("{$table}.{$keyColumn}", $key)
            ->value(DB::raw(str_replace('{row}', $table, $expression)));

        return $vessel === null ? null : (int) $vessel;
    }

    /**
     * On the cloud: why this row must not be deleted, or null when it may.
     * It must not when it is the own record of a vessel that runs its own
     * server. Elsewhere (a vessel server, or a vessel without its own
     * server, whose only copy is the cloud's) nothing changes.
     */
    public function cloudDeleteRefusal(string $table, array $row, string $keyColumn = 'id'): ?string
    {
        $mode = app(AppMode::class);
        if (! $mode->isCloud() || ! config('navisuite.cloud.lock_offline_vessels', true) || ! $this->registry->isSynced($table)) {
            return null;
        }

        try {
            $vessel = $this->vesselOf($table, $keyColumn, $row[$keyColumn] ?? null);
        } catch (Throwable) {
            $vessel = null;   // a table or column missing in a partial schema: nothing to protect
        }
        if ($vessel === null || ! $mode->vesselIsOfflineEnabled($vessel) || ($why = $this->reason($table, $row, $vessel)) === null) {
            return null;
        }

        $label = str_replace('_', ' ', $table);

        return "This {$label} record is the vessel's own record ({$why}). The vessel uses its own onboard server, so it cannot be deleted on the cloud. It is kept as it is.";
    }
}
