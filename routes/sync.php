<?php

use App\Http\Controllers\Sync\SyncApiController;
use App\Http\Controllers\Sync\SyncFileController;
use Illuminate\Support\Facades\Route;

/*
| Cloud-vessel sync API, protocol version 1. Served only by the cloud
| (App\Http\Middleware\AuthenticateSyncNode answers 404 on a vessel). No
| session, cookies or CSRF: each call authenticates as a sync node.
| See docs/checkpoint-5-sync-engine.md.
*/

Route::prefix('api/sync/v1')
    ->middleware([\App\Http\Middleware\AuthenticateSyncNode::class, 'throttle:sync'])
    ->group(function () {
        Route::post('handshake', [SyncApiController::class, 'handshake']);
        Route::post('push', [SyncApiController::class, 'push']);
        Route::get('pull', [SyncApiController::class, 'pull']);
        Route::post('ack', [SyncApiController::class, 'ack']);

        // Files of synchronised records (Checkpoint 7).
        Route::get('files/wanted', [SyncFileController::class, 'wanted']);
        Route::put('files/{table}/{uuid}/{column}', [SyncFileController::class, 'upload'])
            ->where(['table' => '[a-z_]{1,64}', 'uuid' => '[0-9a-fA-F-]{36}', 'column' => '[a-z_]{1,64}']);
        Route::get('files/{table}/{uuid}/{column}', [SyncFileController::class, 'download'])
            ->where(['table' => '[a-z_]{1,64}', 'uuid' => '[0-9a-fA-F-]{36}', 'column' => '[a-z_]{1,64}']);
    });
