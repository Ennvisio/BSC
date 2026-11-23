<?php

use Illuminate\Foundation\Inspiring;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

// Scheduled tasks for the cloud and for vessel servers: see
// App\Console\VesselSchedule. The cloud's schedule is unchanged (the nightly
// PMS refresh). Needs `* * * * * php artisan schedule:run` on the cloud, and
// the NaviSuite-Scheduler service on a vessel server.
App\Console\VesselSchedule::register(
    app(Illuminate\Console\Scheduling\Schedule::class),
    app(App\Support\AppMode::class)
);
