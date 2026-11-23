<?php

namespace App\Support;

use App\User;
use Illuminate\Http\Request;

/**
 * ADR-006 (2026-11-23): vessel users do not delete records.
 *
 * Every web action that deletes, retires, disables or permanently removes a
 * record is listed in DELETE_PATHS. They are refused:
 *
 *  - to vessel users (any role whose user_type is "ship"), wherever they
 *    sign in, on the cloud or on their vessel's server;
 *  - to everyone on a vessel server. Only that vessel's crew and read-only
 *    support accounts can sign in there.
 *
 * Office users keep exactly the delete actions they had (ADR-006 point 5).
 *
 * Deliberately not listed:
 *  - /user/delete. It blocks a user rather than deleting a record. It is
 *    ADR-004's Superadmin action, checked in HomeController.
 *  - The draft edits in PD-72 (removing a line from an unsubmitted
 *    requisition draft, unlinking an attachment from a draft line, taking a
 *    PMS part off a draft) and PMS job deactivation. They stay as they are
 *    until that decision is made.
 *  - /restore, which brings a record back rather than removing it.
 */
final class DeletionPolicy
{
    /** Request paths, in Request::is() form. All are POST routes. */
    public const DELETE_PATHS = [
        // Master data (ADR-003 lists) and office reference data.
        'budget-group/delete',
        'certificate-category/delete',
        'item/delete',
        'category/delete',
        'pms/master/*/*/delete',
        // Vessel records.
        'survey/delete',
        'certificate/delete',
        'vessel/delete',
        'vessel/toggle-status',
        'vessel/equipment/delete',
        'pms/components/*/delete',
        'pms/readings/*/delete',
        'pms/parts/*/delete',
        // Files.
        'attachments/*/delete',
        // Super-admin's trash.
        'permanent-delete',
    ];

    public const VESSEL_USER_MESSAGE = 'Vessel users cannot delete records. If something must be removed, please ask the office.';

    public const VESSEL_SERVER_MESSAGE = 'Records cannot be deleted on the vessel server. If something must be removed, please ask the office.';

    public function __construct(private AppMode $mode)
    {
    }

    /** A user whose role belongs to a ship (master, chief engineer, chief officer, second engineer, operator). */
    public static function isVesselUser(?User $user): bool
    {
        return ($user?->role?->user_type ?? null) === 'ship';
    }

    /** Whether this request is one of the delete actions. */
    public function isDeleteRequest(Request $request): bool
    {
        return ! in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)
            && $request->is(...self::DELETE_PATHS);
    }

    /** Whether this user may use the delete actions on this server at all. */
    public function userMayDelete(?User $user): bool
    {
        return $user !== null && $this->refusal($user) === null;
    }

    /** Why this user may not delete here, or null if they may (subject to each action's own checks). */
    public function refusal(?User $user): ?string
    {
        if ($this->mode->isVessel()) {
            return self::VESSEL_SERVER_MESSAGE;
        }
        if (self::isVesselUser($user)) {
            return self::VESSEL_USER_MESSAGE;
        }

        return null;
    }
}
