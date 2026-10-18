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

// PMS: DueSoon/Overdue depend on today's date, so open jobs are re-evaluated
// every night. Needs the usual `* * * * * php artisan schedule:run` cron.
Illuminate\Support\Facades\Schedule::command('pms:refresh-jobs')->dailyAt('00:15')->withoutOverlapping();
