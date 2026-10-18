<?php

namespace App\Http\Controllers\Concerns;

use App\Vessel;
use Illuminate\Http\Request;

/**
 * Who can see and change what in the PMS module. This app has no role
 * middleware, so - like every other module - it's enforced in the
 * controllers, from these few rules:
 *
 *  - Master data (ranks, locations, categories, job types, conditions,
 *    manufacturers) is fleet-wide reference data: super-admin maintains it.
 *  - A vessel's component tree and running hours belong to that vessel:
 *    its Master and officers maintain their own and can't see anyone
 *    else's. Every ship role on board can at least view it.
 *  - Shore (GM/SRD officers, the superintendents) can look at any vessel's
 *    tree read-only; super-admin can also edit any vessel's.
 */
trait PmsAccess
{
    /** Ship roles that maintain their own vessel's PMS data. */
    protected static array $pmsShipEditors = ['master', 'chief-engineer', 'chief-officer', 'second-engineer'];

    /** Shore roles that can view any vessel's PMS data. */
    protected static array $pmsFleetViewers = [
        'super-admin', 'gm-srd', 'dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd',
        'technical-superintendent', 'marine-superintendent',
    ];

    protected function pmsRole(): ?string
    {
        return auth()->user()->role->role ?? null;
    }

    protected function pmsIsShip(): bool
    {
        return (auth()->user()->role->user_type ?? null) === 'ship';
    }

    protected function canManagePmsMasterData(): bool
    {
        return $this->pmsRole() === 'super-admin';
    }

    protected function authorizePmsMasterData(): void
    {
        abort_unless($this->canManagePmsMasterData(), 403, 'Only super-admin maintains PMS master data.');
    }

    protected function authorizePmsView(): void
    {
        abort_unless(
            $this->pmsIsShip() || in_array($this->pmsRole(), self::$pmsFleetViewers, true),
            403,
            'You do not have access to the PMS module.'
        );
    }

    /** Can the current user change this vessel's components and readings? */
    protected function canEditPmsVessel(int $vesselId): bool
    {
        if ($this->pmsRole() === 'super-admin') {
            return true;
        }

        return $this->pmsIsShip()
            && in_array($this->pmsRole(), self::$pmsShipEditors, true)
            && (int) auth()->user()->role->vessel_id === $vesselId;
    }

    protected function authorizePmsEdit(int $vesselId): void
    {
        abort_unless($this->canEditPmsVessel($vesselId), 403, 'You cannot change this vessel\'s PMS records.');
    }

    /**
     * Raising a requisition is the requisition module's own rule (its 'member'
     * middleware): the vessel's chief officer or second engineer, for their own vessel.
     */
    protected function canRequisitionForVessel(int $vesselId): bool
    {
        return $this->pmsIsShip()
            && in_array($this->pmsRole(), ['chief-officer', 'second-engineer'], true)
            && (int) auth()->user()->role->vessel_id === $vesselId;
    }

    /** Approving a completed job is an office sign-off: shore PMS roles only, never the ship that did the work. */
    protected function canApprovePmsJobs(): bool
    {
        return ! $this->pmsIsShip() && in_array($this->pmsRole(), self::$pmsFleetViewers, true);
    }

    /**
     * The vessel being looked at. A ship user is always pinned to their own
     * - never read from the request, so they can't page through another
     * ship's equipment by editing the URL. Shore users pick one, defaulting
     * to the first active vessel.
     */
    protected function pmsVessel(Request $request): Vessel
    {
        if ($this->pmsIsShip()) {
            return Vessel::findOrFail(auth()->user()->role->vessel_id);
        }

        $vessel = $request->filled('vessel_id')
            ? Vessel::where('status', true)->find((int) $request->vessel_id)
            : null;

        return $vessel ?? Vessel::where('status', true)->orderBy('name')->firstOrFail();
    }

    /** The vessel a stored record belongs to must be one this user can view. */
    protected function authorizePmsVesselView(int $vesselId): void
    {
        $this->authorizePmsView();

        if ($this->pmsIsShip()) {
            abort_unless((int) auth()->user()->role->vessel_id === $vesselId, 403);
        }
    }
}
