<?php

namespace App\Console\Commands;

use App\Sync\Engine\SyncMaintenance;
use Illuminate\Console\Command;
use Throwable;

class SyncResend extends Command
{
    protected $signature = 'sync:resend {table} {--vessel= : Only rows of this vessel} {--force : Do not ask}';

    protected $description = 'Queue every current row of a table to be sent again (after enabling a table for sync)';

    public function handle(SyncMaintenance $maintenance): int
    {
        $table = (string) $this->argument('table');
        $vessel = $this->option('vessel') === null ? null : (int) $this->option('vessel');

        if (! $this->option('force') && ! $this->confirm("Send every row of {$table} again at the next sync?", true)) {
            return self::FAILURE;
        }

        try {
            $count = $maintenance->resend($table, $vessel);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$count} rows of {$table} will be sent at the next sync.");

        return self::SUCCESS;
    }
}
