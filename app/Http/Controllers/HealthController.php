<?php

namespace App\Http\Controllers;

use App\Support\AppMode;
use App\Support\HealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * GET /health. Outside the web middleware group: no session, no cookies, no
 * login, and it still answers when the app is misconfigured, so it can say
 * so. HTTP 200 for ok or degraded, 503 for down.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request, AppMode $mode, HealthCheck $health): JsonResponse
    {
        $enabled = config('navisuite.health.enabled');
        abort_unless($enabled === null ? $mode->isVessel() : (bool) $enabled, 404);

        $result = $health->run();

        $body = [
            'status' => $result['status'],
            'app' => 'NaviSuite',
            'mode' => $mode->mode(),
            'vessel' => $result['vessel'],
            'release' => config('navisuite.release'),
            // Checkpoint 8: lets a desktop client refuse a server it cannot
            // work with, or tell the user it needs updating.
            'clients' => [
                'api' => (int) config('navisuite.compat.client_api', 1),
                'min_version' => (string) config('navisuite.compat.min_client_version', '1.0.0'),
            ],
            'time' => now()->toIso8601String(),
        ];

        if ($this->maySeeDetails($request)) {
            $body['checks'] = $result['checks'];
        }

        return response()
            ->json($body, $result['status'] === HealthCheck::DOWN ? 503 : 200)
            ->header('Cache-Control', 'no-store');
    }

    private function maySeeDetails(Request $request): bool
    {
        $ip = (string) $request->ip();

        if (IpUtils::checkIp($ip, ['127.0.0.1', '::1'])) {
            return true;
        }

        $ranges = array_values(array_filter(array_map('trim', explode(',', (string) config('navisuite.health.detail_ips', '')))));
        if ($ranges !== [] && IpUtils::checkIp($ip, $ranges)) {
            return true;
        }

        $token = (string) config('navisuite.health.token', '');

        return $token !== '' && hash_equals($token, (string) $request->header('X-Health-Token', ''));
    }
}
