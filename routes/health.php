<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
| GET /health, for desktop clients, install scripts and monitoring. Loaded
| outside the `web` group (see bootstrap/app.php), so it needs no session or
| login. On the cloud it answers 404 unless APP_HEALTH_ENABLED=true.
*/
Route::get('/health', HealthController::class)->name('health');
