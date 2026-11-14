<?php

namespace App\Providers;

use App\Support\PmsAccessGate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Keep the cached sidebar/dashboard counts current: any save or
        // delete through Eloquent clears that model's cached count.
        foreach (\App\Support\NavCounts::MODELS as $key => $model) {
            $forget = fn () => \App\Support\NavCounts::forget($key);
            $model::saved($forget);
            $model::deleted($forget);
        }

        // The module switcher's PMS tab needs "can this user see the PMS
        // module" - shown in both the main topbar and the PMS layout.
        View::composer('partials.module-switch', function ($view) {
            $view->with('canViewPms', (new PmsAccessGate)->canView());
        });
    }
}
