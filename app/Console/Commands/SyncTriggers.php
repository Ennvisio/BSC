<?php

namespace App\Console\Commands;

use App\Sync\SyncLogger;
use App\Sync\TriggerManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Installs, removes or reports the change-capture triggers. Installing is
 * idempotent (each trigger is dropped and recreated) and all or nothing.
 * Run it again after any migration that renames or drops a column the
 * triggers use (vessel_id, order_id and the like; see config/sync.php).
 */
class SyncTriggers extends Command
{
    protected $signature = 'sync:triggers {action=status : install, drop or status}';

    protected $description = 'Install, remove or check the database triggers that record changes for sync';

    public function handle(TriggerManager $triggers, SyncLogger $logger): int
    {
        return match ($this->argument('action')) {
            'install' => $this->install($triggers, $logger),
            'drop' => $this->drop($triggers, $logger),
            'status' => $this->status($triggers),
            default => $this->invalid(),
        };
    }

    private function install(TriggerManager $triggers, SyncLogger $logger): int
    {
        try {
            $count = $triggers->install();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->line('On MySQL the database account needs the TRIGGER privilege. With binary logging on, it may also need SUPER, or log_bin_trust_function_creators=1.');
            $this->tryLog($logger, 'install-triggers', 'error', $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Change capture is installed on {$count} tables.");

        $skipped = $triggers->tablesWithMissingReferences();
        if ($skipped !== []) {
            $this->warn('Not captured, because a table their vessel lookup reads is missing: '.implode(', ', $skipped).'.');
        }
        $this->tryLog($logger, 'install-triggers', 'ok', "Installed on {$count} tables.");

        return self::SUCCESS;
    }

    private function drop(TriggerManager $triggers, SyncLogger $logger): int
    {
        $count = $triggers->drop();
        $this->warn("Removed {$count} sync triggers. Changes are no longer recorded for sync.");
        $this->tryLog($logger, 'drop-triggers', 'warning', "Removed {$count} triggers.");

        return self::SUCCESS;
    }

    private function status(TriggerManager $triggers): int
    {
        $rows = [];
        foreach ($triggers->status() as $table => $s) {
            $rows[] = [$table, "{$s['installed']} / {$s['expected']}", $s['installed'] === $s['expected'] ? 'yes' : 'NO'];
        }

        $this->table(['Table', 'Triggers', 'Captured'], $rows);

        if ($triggers->isFullyInstalled()) {
            $this->info('Change capture is installed on every synchronised table.');

            return self::SUCCESS;
        }

        $this->warn('Change capture is not fully installed. Run "php artisan sync:triggers install".');

        return self::FAILURE;
    }

    private function invalid(): int
    {
        $this->error('The action must be install, drop or status.');

        return self::FAILURE;
    }

    /** Logging must never turn a successful install into a failure. */
    private function tryLog(SyncLogger $logger, string $action, string $status, string $message): void
    {
        try {
            $logger->record($action, $status, ['message' => $message]);
        } catch (Throwable) {
            // sync_logs may not exist yet if the migrations have not run.
        }
    }
}
