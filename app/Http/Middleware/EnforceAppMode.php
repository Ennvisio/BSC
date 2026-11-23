<?php

namespace App\Http\Middleware;

use App\Support\AppMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the deployment mode (config/navisuite.php) to every web request.
 * It is appended to the `web` middleware group, so no controller needed to
 * change.
 *
 * Cloud mode: nothing changes, except that ship crew of a vessel that has
 * been switched to its own onboard server can only view, not change, data.
 *
 * Vessel mode: only this vessel's crew and the listed support accounts may
 * use the server, support accounts are read-only, and office-maintained data
 * cannot be changed on board.
 */
class EnforceAppMode
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private AppMode $mode)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $errors = $this->mode->configurationErrors();
        if ($errors !== []) {
            // Refuse to serve rather than guess. A vessel server without a
            // vessel id cannot tell its own crew from anyone else's.
            report(new \RuntimeException('NaviSuite is misconfigured: '.implode(' ', $errors)));

            abort(503, 'This NaviSuite server is not configured correctly. Ask your administrator to run "php artisan navisuite:status".');
        }

        return $this->mode->isVessel()
            ? $this->handleVessel($request, $next)
            : $this->handleCloud($request, $next);
    }

    private function handleVessel(Request $request, Closure $next): Response
    {
        if ($request->is(...config('navisuite.vessel.blocked_paths', []))) {
            return $this->deny($request, 'This is not available on the vessel server. Please contact the office.');
        }

        $user = $request->user();

        if ($user !== null && ! $this->mode->userMayUseThisServer($user)) {
            // A session from before the server was switched to vessel mode,
            // or a remember-me cookie. The login form itself is guarded by
            // the Validated listener in AppModeServiceProvider.
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Your account cannot be used on this vessel\'s server.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 401)
                : redirect()->route('login')->withErrors(['email' => $message]);
        }

        if (! $this->isWrite($request)) {
            return $next($request);
        }

        if ($request->is(...config('navisuite.vessel.blocked_write_paths', []))) {
            return $this->deny($request, 'This information is maintained by the office and cannot be changed on board. Changes made in the office will reach the vessel by synchronisation.');
        }

        if ($user !== null && $this->mode->isSupportAccount($user)) {
            return $this->deny($request, 'Support accounts are read-only on the vessel server.');
        }

        return $next($request);
    }

    private function handleCloud(Request $request, Closure $next): Response
    {
        if (! config('navisuite.cloud.lock_offline_vessels', true) || ! $this->isWrite($request)) {
            return $next($request);
        }

        $role = $request->user()?->role;

        if ($role !== null
            && $role->user_type === 'ship'
            && $role->vessel_id
            && $this->mode->vesselIsOfflineEnabled((int) $role->vessel_id)) {
            return $this->deny($request, 'Your vessel now uses its own onboard NaviSuite server. Please make changes there. The office copy is read-only for your vessel.');
        }

        return $next($request);
    }

    /** A request that changes data, other than the few POST routes that only read. */
    private function isWrite(Request $request): bool
    {
        return ! in_array($request->getMethod(), self::READ_METHODS, true)
            && ! $request->is(...config('navisuite.read_only_write_paths', []));
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message], 403);
        }

        abort(403, $message);
    }
}
