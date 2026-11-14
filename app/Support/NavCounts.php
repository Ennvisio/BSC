<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The record counts shown in the sidebar badges and the dashboard cards.
 * Every page load used to run these as separate COUNT queries; now each is
 * cached briefly. The cache is also cleared whenever a record of that model
 * is saved or deleted through Eloquent (see AppServiceProvider::boot()), so
 * badges stay current; the short TTL only covers writes that bypass model
 * events (bulk imports, raw updates).
 *
 * Every counted model is "status = true" (the app's soft-delete flag).
 */
class NavCounts
{
    private const TTL_SECONDS = 60;

    /** key => model class */
    public const MODELS = [
        'certificates' => \App\VesselCertificate::class,
        'surveys' => \App\VesselSurvey::class,
        'vessels' => \App\Vessel::class,
        'categories' => \App\Category::class,
        'certificate_categories' => \App\CertificateCategory::class,
        'budget_groups' => \App\BudgetGroup::class,
        'items' => \App\Item::class,
    ];

    public static function get(string $key): int
    {
        $model = self::MODELS[$key];

        $count = fn () => $model::where('status', true)->count();

        // A cache that can't be read or written (unwritable storage/framework/cache,
        // Redis down) must slow a page down, never take it down.
        try {
            return (int) Cache::remember(self::cacheKey($key), self::TTL_SECONDS, $count);
        } catch (\Throwable $e) {
            report($e);

            return (int) $count();
        }
    }

    public static function forget(string $key): void
    {
        try {
            Cache::forget(self::cacheKey($key));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function cacheKey(string $key): string
    {
        return 'nav-count:'.$key;
    }
}
