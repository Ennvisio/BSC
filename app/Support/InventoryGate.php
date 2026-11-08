<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Who can see the Inventory module, and which vessel they're scoped to -
 * the single source of truth StockReportController's own FLEET_WIDE_ROLES
 * used to duplicate.
 *
 * Every ship role (chief-officer/second-engineer included, not just Master/
 * Chief Engineer) can see their own vessel's read-only Inventory pages -
 * wider than Stock Report's own access (which stays Master/Chief Engineer
 * only, unchanged, since that's an existing screen this doesn't widen).
 * Shore sees any vessel, but only the same roles that already act on
 * requisitions fleet-wide.
 */
class InventoryGate
{
    public const FLEET_WIDE_ROLES = [
        'gm-srd', 'dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd',
        'dgm-ssm', 'agm-ssm', 'am-ssm', 'superintendent-ssm', 'super-admin',
    ];

    public function canView(): bool
    {
        if (! auth()->check() || empty(auth()->user()->role)) {
            return false;
        }

        $role = auth()->user()->role;

        return ($role->user_type ?? null) === 'ship' || in_array($role->role ?? null, self::FLEET_WIDE_ROLES, true);
    }

    public function isFleetWide(): bool
    {
        return in_array(auth()->user()->role->role ?? null, self::FLEET_WIDE_ROLES, true);
    }

    /**
     * The vessel this request is scoped to. A ship user's own vessel always
     * wins; a fleet-wide viewer has to choose one via ?vessel_id=.
     */
    public function resolveVesselId(Request $request): int
    {
        if (! $this->isFleetWide()) {
            return (int) auth()->user()->role->vessel_id;
        }

        $vesselId = (int) $request->query('vessel_id');
        abort_unless(
            $vesselId > 0 && \App\Vessel::where('id', $vesselId)->where('status', true)->exists(),
            422,
            'Choose a vessel first.'
        );

        return $vesselId;
    }

    /**
     * Same vessel a ship user is locked to, but null ("the whole fleet")
     * rather than a hard 422 for a fleet-wide viewer who hasn't narrowed to
     * one - for reports that make sense summed across every vessel, unlike
     * Current Stock/the item card, which are always exactly one vessel's
     * own figures.
     */
    public function resolveOptionalVesselId(Request $request): ?int
    {
        if (! $this->isFleetWide()) {
            return (int) auth()->user()->role->vessel_id;
        }

        $vesselId = (int) $request->query('vessel_id');

        return $vesselId > 0 ? $vesselId : null;
    }
}
