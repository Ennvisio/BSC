<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Caches the PMS Dashboard's widgets for 5 minutes, one cache entry per
 * widget per scope (a vessel, or "fleet" for the all-vessels view).
 *
 * Each scope's entries are keyed with a version token; bump() replaces that
 * token, which makes every entry under the old one unreachable without
 * having to enumerate or tag them individually - needed because this app's
 * cache driver (file) doesn't support tags. Call bump() wherever a job or
 * reading is saved (see PmsJobScheduler::refresh()/complete()/approve()/
 * createJob()/refreshComponent()) so a save shows up the moment the next
 * dashboard page loads, without waiting out the 5 minutes.
 */
class PmsDashboardCache
{
    const TTL = 300;

    /** @param  \Closure():mixed  $callback */
    public static function remember(?int $vesselId, string $widget, \Closure $callback): mixed
    {
        $scope = $vesselId ?? 'fleet';
        $version = self::version($scope);

        return Cache::remember("pms:dash:{$scope}:{$version}:{$widget}", self::TTL, $callback);
    }

    /** Invalidate one vessel's dashboard widgets - and the fleet view, which aggregates every vessel - or every scope, with no vessel. */
    public static function bump(?int $vesselId = null): void
    {
        Cache::forever(self::versionKey($vesselId ?? 'fleet'), Str::random(10));
        if ($vesselId !== null) {
            Cache::forever(self::versionKey('fleet'), Str::random(10));
        }
    }

    private static function version(string|int $scope): string
    {
        return Cache::rememberForever(self::versionKey($scope), fn () => Str::random(10));
    }

    private static function versionKey(string|int $scope): string
    {
        return "pms:dash:v:{$scope}";
    }
}
