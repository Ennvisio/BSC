<?php

namespace Tests\Feature\AppMode;

use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsRequisitionSchema;

/**
 * Shared set-up for the deployment-mode tests: the users/roles/vessels
 * schema, plus two probe routes in the real `web` middleware group so the
 * mode rules can be tested without depending on any one controller.
 */
trait BuildsAppModeFixtures
{
    use BuildsRequisitionSchema;

    protected function setUpAppModeFixtures(): void
    {
        $this->buildRequisitionSchema();

        Route::middleware('web')->group(function () {
            Route::get('/__mode-probe', fn () => 'read ok');
            Route::post('/__mode-probe', fn () => 'write ok');
        });
    }

    protected function useMode(string $mode, ?int $vesselId = null, string $supportAccounts = ''): void
    {
        config([
            'navisuite.mode' => $mode,
            'navisuite.vessel_id' => $vesselId,
            'navisuite.support_accounts' => $supportAccounts,
        ]);
    }

    /** Runs the new migration, as `php artisan migrate` will on a real server. */
    protected function addOfflineColumn(): void
    {
        (require database_path('migrations/2026_11_20_100000_add_offline_enabled_at_to_vessels_table.php'))->up();
    }
}
