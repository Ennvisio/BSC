<?php

namespace App\Http\Middleware;

use App\Support\DeletionPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ADR-006: refuses the delete actions listed in DeletionPolicy to vessel
 * users, and to everyone on a vessel server. Appended to the `web` group and
 * run before route-model binding, so a refused request loads nothing.
 *
 * The screens also stop offering these buttons to vessel users
 * (partials/deletion-policy-styles), but this check is the protection.
 */
class RefuseVesselUserDeletes
{
    public function __construct(private DeletionPolicy $policy)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->policy->isDeleteRequest($request)) {
            return $next($request);
        }

        $user = $request->user();
        if ($user === null) {
            // Not signed in: the controllers' own `auth` check answers, as before.
            return $next($request);
        }

        $refusal = $this->policy->refusal($user);
        if ($refusal === null) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $refusal], 403);
        }

        abort(403, $refusal);
    }
}
