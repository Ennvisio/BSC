<?php

namespace App\Console\Commands;

use App\Services\StockService;
use App\Vessel;
use Illuminate\Console\Command;

/**
 * One-time (but safe to re-run) reconstruction of the stock_movements ledger
 * for stock that moved before the ledger existed - see
 * StockService::backfillMovementsForVessel() for exactly how it rebuilds
 * each item's history and why running it twice changes nothing the second
 * time.
 */
class StockBackfillMovements extends Command
{
    protected $signature = 'stock:backfill-movements {--vessel= : Only this vessel id}';

    protected $description = 'Reconstruct the stock_movements ledger from existing opening balances, receipts and consumptions';

    public function handle(StockService $stock): int
    {
        $vessels = $this->option('vessel')
            ? Vessel::where('id', (int) $this->option('vessel'))->get(['id', 'name'])
            : Vessel::orderBy('name')->get(['id', 'name']);

        if ($vessels->isEmpty()) {
            $this->error('No matching vessel.');

            return self::FAILURE;
        }

        $totals = ['opening' => 0, 'receipt' => 0, 'consumption' => 0, 'adjustment' => 0];

        foreach ($vessels as $vessel) {
            $counts = $stock->backfillMovementsForVessel($vessel->id);
            $written = array_sum($counts);

            $this->line("{$vessel->name}: {$written} movement(s) written "
                ."(opening {$counts['opening']}, receipt {$counts['receipt']}, "
                ."consumption {$counts['consumption']}, adjustment {$counts['adjustment']}).");

            foreach ($counts as $type => $count) {
                $totals[$type] += $count;
            }
        }

        $this->info('Total: '.array_sum($totals).' movement(s) written across '.$vessels->count().' vessel(s).');

        return self::SUCCESS;
    }
}
