<?php

namespace App\Console\Commands;

use App\Services\PmsJobScheduler;
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

    public function handle(PmsJobScheduler $scheduler): int
    {
        $changed = $scheduler->refreshAll($this->option('vessel') ? (int) $this->option('vessel') : null);
        $this->info("PMS jobs refreshed: {$changed} changed.");

        return self::SUCCESS;
    }
}
