<?php

namespace App\Http\Middleware;

use App\Support\AppMode;
use App\Support\UserAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a user who may no longer use this server (Checkpoint
 * 7): deactivated in the office, or (on a vessel server) moved to another
 * vessel or a shore role. On a vessel these changes arrive with the next
 * sync; the crew member is signed out at their next click instead of
 * keeping a session that was opened before.
 */
class EndRevokedSessions
{
    public function __construct(private AppMode $mode)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user !== null && ($reason = UserAccess::refusal($user, $this->mode)) !== null) {
            Auth::guard()->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $request->expectsJson()
                ? response()->json(['message' => $reason], 401)
                : redirect()->guest(route('login'))->withErrors(['email' => $reason]);
        }

        return $next($request);
    }
}
