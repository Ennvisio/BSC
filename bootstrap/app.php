<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        using: function () {
            // GET /health: no session, cookies or login (see routes/health.php).
            Route::group([], base_path('routes/health.php'));

            // /api/sync/v1: cloud only; each call authenticates as a sync
            // node, with no session or cookies (see routes/sync.php).
            Route::group([], base_path('routes/sync.php'));

            Route::middleware('web')
                ->namespace('App\Http\Controllers')
                ->group(base_path('routes/web.php'));

            Route::prefix('api')
                ->middleware('api')
                ->namespace('App\Http\Controllers')
                ->group(base_path('routes/api.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: []);

        $middleware->validateCsrfTokens(except: []);

        $middleware->trimStrings(except: [
            'password',
            'password_confirmation',
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->alias([
            'member' => \App\Http\Middleware\MemberMiddleWare::class,
            'app.mode' => \App\Http\Middleware\EnforceAppMode::class,
        ]);

        // Cloud or vessel deployment rules (config/navisuite.php). Runs after
        // the session has started, so it can see the signed-in user...
        $middleware->appendToGroup('web', \App\Http\Middleware\EnforceAppMode::class);
        // Checkpoint 7: a user deactivated (or moved off this vessel) since
        // signing in is signed out at the next request.
        $middleware->appendToGroup('web', \App\Http\Middleware\EndRevokedSessions::class);
        // ADR-006: vessel users do not delete records (App\Support\DeletionPolicy).
        $middleware->appendToGroup('web', \App\Http\Middleware\RefuseVesselUserDeletes::class);
        // ...but before route-model binding, so a blocked request is refused
        // without loading (or 404-ing on) the records in its URL.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\EnforceAppMode::class,
        );
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\RefuseVesselUserDeletes::class,
        );
        // Checkpoint 14: who made each change, recorded with its sync event.
        $middleware->appendToGroup('web', \App\Http\Middleware\SetSyncActor::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create()
    ->usePublicPath(dirname(__DIR__));
