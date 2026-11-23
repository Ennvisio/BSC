<?php

namespace App\Console\Commands;

use App\Support\AppMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a vessel as running its own onboard server, or clears the mark. Cloud
 * only. While the mark is set, that vessel's ship crew can view but not
 * change data on the cloud (App\Http\Middleware\EnforceAppMode).
 *
 * Only switch a vessel over once its onboard server is in service and sync is
 * running. Until then the crew's onboard work would not reach the office.
 * The change is reversible with --disable and touches no other data.
 */
class NaviSuiteVesselOffline extends Command
{
    protected $signature = 'navisuite:vessel-offline
        {vessel : The vessel id}
        {--disable : Clear the mark, so the crew can make changes on the cloud again}
        {--force : Do not ask for confirmation}';

    protected $description = 'Mark a vessel as running its own onboard server (cloud only), or clear the mark';

    public function handle(AppMode $mode): int
    {
        if (! $mode->isCloud()) {
            $this->error('This command only runs on the cloud server (APP_MODE=cloud).');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('vessels', 'offline_enabled_at')) {
            $this->error('The vessels.offline_enabled_at column is missing. Run "php artisan migrate" first.');

            return self::FAILURE;
        }

        $id = (string) $this->argument('vessel');
        $vessel = ctype_digit($id)
            ? DB::table('vessels')->where('id', (int) $id)->first(['id', 'name', 'offline_enabled_at'])
            : null;

        if ($vessel === null) {
            $this->error("No vessel with id {$id}.");

            return self::FAILURE;
        }

        $enable = ! $this->option('disable');
        $isEnabled = $vessel->offline_enabled_at !== null;

        if ($enable === $isEnabled) {
            $this->info($enable
                ? "{$vessel->name} has been marked as offline-enabled since {$vessel->offline_enabled_at}. Nothing to do."
                : "{$vessel->name} is not marked as offline-enabled. Nothing to do.");

            return self::SUCCESS;
        }

        $question = $enable
            ? "Mark {$vessel->name} as running its own onboard server? Its ship crew will become read-only on the cloud."
            : "Clear the onboard-server mark for {$vessel->name}? Its ship crew will be able to make changes on the cloud again.";

        if (! $this->option('force') && ! $this->confirm($question)) {
            $this->line('Cancelled. Nothing was changed.');

            return self::FAILURE;
        }

        DB::table('vessels')->where('id', $vessel->id)->update([
            'offline_enabled_at' => $enable ? now() : null,
        ]);

        $this->info($enable
            ? "{$vessel->name} is now marked as offline-enabled."
            : "{$vessel->name} is no longer marked as offline-enabled.");

        return self::SUCCESS;
    }
}
