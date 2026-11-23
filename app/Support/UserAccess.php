<?php

namespace App\Support;

use App\User;

/**
 * Whether a user may sign in, or stay signed in, on this server.
 *
 * - Deactivated users (users.status false: "Delete user" in the office)
 *   may not sign in anywhere. Before Checkpoint 7 they still could, as
 *   sign-in never looked at the flag.
 * - On a vessel server, only that vessel's crew (and support accounts),
 *   see AppMode::userMayUseThisServer.
 */
class UserAccess
{
    public static function refusal(User $user, AppMode $mode): ?string
    {
        if (config('navisuite.auth.block_deactivated', true) && $user->status !== null && ! (bool) $user->status) {
            return 'This account has been deactivated.';
        }
        if ($mode->isVessel() && ! $mode->userMayUseThisServer($user)) {
            return 'This account cannot sign in on this vessel\'s server. Only this vessel\'s crew can sign in here.';
        }

        return null;
    }
}
