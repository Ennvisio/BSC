<?php

namespace App\Sync\Engine;

use App\Sync\SyncState;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

/**
 * Gives outbox events their position in the stream (sync_outbox.seq).
 *
 * The outbox id is taken when a row is inserted, but transactions commit in
 * a different order: id 7 can become visible after id 8 has already been
 * sent. A cursor over ids would then skip id 7 for ever (Checkpoint 2, open
 * issue 3). Instead, this class numbers events only once they are visible,
 * so they are committed, one process at a time. Anything that commits later
 * gets a higher number, and no cursor can pass over it.
 *
 * The last number given is also kept in sync_state, because sync:prune
 * deletes old events: the numbering must carry on from where it was, not
 * from the highest number still in the table, or new events would get
 * positions that every cursor has already passed.
 */
class OutboxSequencer
{
    private const LOCK = 'navisuite_sync_sequencer';

    private const CHUNK = 1000;

    public const HIGH_WATER = 'outbox.high_water';

    public function __construct(private SyncState $state)
    {
    }

    /** Numbers every committed event that has no number yet. Returns how many. */
    public function sequence(int $max = 100000): int
    {
        $connection = DB::connection();
        $mysql = $connection->getDriverName() === 'mysql';

        if ($mysql && $connection->transactionLevel() > 0) {
            // Inside a transaction it could number this transaction's own,
            // uncommitted events, which other processes cannot see yet.
            throw new LogicException('Sync events must be numbered outside a database transaction.');
        }

        if ($mysql && (int) $connection->scalar('SELECT GET_LOCK(?, 10)', [self::LOCK]) !== 1) {
            throw new RuntimeException('Another process is numbering sync events. Try again shortly.');
        }

        try {
            $total = 0;
            while ($total < $max) {
                $done = $this->chunk($mysql);
                $total += $done;
                if ($done < self::CHUNK) {
                    break;
                }
            }

            return $total;
        } finally {
            if ($mysql) {
                $connection->scalar('SELECT RELEASE_LOCK(?)', [self::LOCK]);
            }
        }
    }

    /** The highest number given so far: everything up to here is in the stream. */
    /** The last position given out, even if pruning has since removed that event. */
    public function highWater(): int
    {
        return max((int) DB::table('sync_outbox')->max('seq'), (int) $this->state->get(self::HIGH_WATER, 0));
    }

    private function chunk(bool $mysql): int
    {
        if ($mysql) {
            // Read committed: no gap locks, so the capture triggers' inserts
            // are never held up while numbers are given.
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }

        return DB::transaction(function () {
            $ids = DB::table('sync_outbox')->whereNull('seq')->orderBy('id')->limit(self::CHUNK)->pluck('id')->all();
            if ($ids === []) {
                return 0;
            }

            $next = $this->highWater();
            $cases = [];
            $bindings = [];
            foreach ($ids as $id) {
                $cases[] = 'WHEN ? THEN ?';
                array_push($bindings, $id, ++$next);
            }

            foreach (array_chunk(array_keys($ids), 500) as $slice) {
                $sliceIds = array_map(fn ($i) => $ids[$i], $slice);
                $sliceCases = implode(' ', array_map(fn ($i) => $cases[$i], $slice));
                $sliceBindings = [];
                foreach ($slice as $i) {
                    array_push($sliceBindings, $bindings[$i * 2], $bindings[$i * 2 + 1]);
                }
                DB::update(
                    'UPDATE sync_outbox SET seq = CASE id '.$sliceCases.' END WHERE id IN ('.implode(',', array_fill(0, count($sliceIds), '?')).')',
                    [...$sliceBindings, ...$sliceIds]
                );
            }
            $this->state->put(self::HIGH_WATER, $next);

            return count($ids);
        });
    }
}
