<?php

return [
    // First, so the credentials it fills in are there for everything else.
    App\Providers\SecretsServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\AppModeServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\SyncServiceProvider::class,
];
