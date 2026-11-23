<?php

namespace App\Console\Commands;

use App\Sync\SyncHealth;
use Illuminate\Console\Command;

/**
 * Sync failure monitoring (Checkpoint 7). Lists what needs a person; with
 * --log (the scheduler, every 15 minutes) writes each new problem once to
 * the sync log and sync_logs. Exit code 0 when nothing needs attention, 1
 * for warnings, 2 for errors, so scripts and monitors can use it.
 */
class SyncHealthCommand extends Command
{
    protected $signature = 'sync:health {--json : Machine-readable output} {--log : Write new problems to the sync log}';

    protected $description = 'Show sync problems that need attention: failures, stuck changes, conflicts, files, credentials';

    public function handle(SyncHealth $health): int
    {
        $alerts = $health->alerts();
        if ($this->option('log')) {
            $health->logNew($alerts);
        }
        $level = SyncHealth::level($alerts);

        if ($this->option('json')) {
            $this->line(json_encode(['status' => $level, 'alerts' => $alerts], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($alerts === []) {
            $this->info('Sync: nothing needs attention.');
        } else {
            $this->table(['Level', 'Check', 'Problem'], array_map(fn ($a) => [strtoupper($a['level']), $a['check'], $a['message']], $alerts));
        }

        return match ($level) {
            SyncHealth::ERROR => 2,
            SyncHealth::WARNING => 1,
            default => self::SUCCESS,
        };
    }
}
