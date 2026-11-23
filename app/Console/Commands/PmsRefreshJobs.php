<?php

namespace App\Console\Commands;

use App\Services\PmsJobScheduler;
use App\Support\AppMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Console\Command;

/**
 * Recalculates every open PMS job's due date, warning date and status - the
 * calendar moves on even when nobody touches a job, so DueSoon/Overdue have
 * to be re-evaluated daily. Safe to run any number of times.
 */
class PmsRefreshJobs extends Command
{
    protected $signature = 'pms:refresh-jobs {--vessel= : Only this vessel id}';

    protected $description = 'Recalculate due dates, warning dates and statuses of open PMS jobs';

    public function handle(PmsJobScheduler $scheduler, AppMode $mode): int
    {
        $vesselId = $this->option('vessel') ? (int) $this->option('vessel') : null;

        // On the cloud, a vessel with its own server refreshes its own jobs
        // (VesselSchedule on board); doing it here too would make two
        // writers of the same jobs.
        $skip = [];
        if ($mode->isCloud() && config('navisuite.cloud.lock_offline_vessels', true) && Schema::hasColumn('vessels', 'offline_enabled_at')) {
            $skip = DB::table('vessels')->whereNotNull('offline_enabled_at')->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($vesselId !== null && in_array($vesselId, $skip, true)) {
                $this->warn("Vessel {$vesselId} runs its own server; its jobs are refreshed on board.");

                return self::SUCCESS;
            }
        }

        $changed = $scheduler->refreshAll($vesselId, $skip);
        $this->info("PMS jobs refreshed: {$changed} changed.".($skip === [] ? '' : ' Skipped vessels with their own server: '.implode(', ', $skip).'.'));

        return self::SUCCESS;
    }
}
