<?php

namespace App\Providers;

use App\Support\AppMode;
use App\Support\UserAccess;
use Illuminate\Auth\Events\Validated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

/**
 * Wires the deployment mode (cloud or vessel) into the application. See
 * App\Support\AppMode and config/navisuite.php.
 */
class AppModeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppMode::class);
    }

    public function boot(): void
    {
        // Login scoping on a vessel server. Validated fires after the password
        // has been checked and before the session is created, so a user who
        // does not belong on this server is never logged in. The listener is
        // always registered and checks the mode itself, so the mode can be
        // changed at runtime (tests do) without re-registering anything.
        // Checkpoint 7: a deactivated user is refused too, on every server
        // (App\Support\UserAccess).
        Event::listen(Validated::class, function (Validated $event) {
            $mode = $this->app->make(AppMode::class);

            if ($event->user instanceof \App\User && ($reason = UserAccess::refusal($event->user, $mode)) !== null) {
                throw ValidationException::withMessages(['email' => $reason]);
            }
        });
    }
}
