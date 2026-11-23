<?php

namespace App\Console\Commands;

use App\Sync\Models\SyncConflict;
use Illuminate\Console\Command;

class SyncConflicts extends Command
{
    protected $signature = 'sync:conflicts
        {--all : Include resolved conflicts}
        {--limit=50}
        {--reviewed= : Mark this conflict id as reviewed}';

    protected $description = 'List sync conflicts, newest first (the ones needing review by default)';

    public function handle(): int
    {
        if ($id = $this->option('reviewed')) {
            $updated = SyncConflict::query()->whereKey((int) $id)->update(['status' => 'reviewed', 'reviewed_at' => now()]);
            $updated ? $this->info("Conflict {$id} marked as reviewed.") : $this->error("No conflict {$id}.");

            return $updated ? self::SUCCESS : self::FAILURE;
        }

        $rows = SyncConflict::query()
            ->when(! $this->option('all'), fn ($q) => $q->where('status', SyncConflict::STATUS_NEEDS_REVIEW))
            ->orderByDesc('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($rows->isEmpty()) {
            $this->info($this->option('all') ? 'No conflicts recorded.' : 'No conflicts need review. Use --all to see resolved ones.');

            return self::SUCCESS;
        }

        $this->table(['Id', 'When', 'Found by', 'Table', 'Row', 'Kind', 'Rule', 'Outcome', 'Status'], $rows->map(fn ($c) => [
            $c->id, substr((string) $c->created_at, 0, 19), $c->detected_by, $c->table_name, substr($c->row_uuid, 0, 8),
            $c->kind, $c->policy ?? '-', $c->resolution, $c->status,
        ])->all());

        return self::SUCCESS;
    }
}
