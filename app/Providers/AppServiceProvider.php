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

        // The module switcher's PMS tab needs "can this user see the PMS
        // module" - shown in both the main topbar and the PMS layout.
        View::composer('partials.module-switch', function ($view) {
            $view->with('canViewPms', (new PmsAccessGate)->canView());
        });
    }
}
