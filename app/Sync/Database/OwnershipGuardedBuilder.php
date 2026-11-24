<?php

namespace App\Sync\Database;

use App\Sync\WrongSideEdits;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;

/**
 * PD-34 (deletes and edits): the query builder every connection hands out
 * (OwnershipGuardedConnections). Before an insert, update, upsert or
 * delete reaches the database it asks App\Sync\WrongSideEdits whether the
 * write changes the other side's data, so a write made with DB::table(),
 * a mass update or a model goes through the same check, and a new route
 * cannot bypass it. Reads are untouched.
 */
class OwnershipGuardedBuilder extends Builder
{
    public function insert(array $values)
    {
        $this->guardInsert($values);

        return parent::insert($values);
    }

    public function insertOrIgnore(array $values)
    {
        $this->guardInsert($values);

        return parent::insertOrIgnore($values);
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $this->guardInsert($values);

        return parent::insertGetId($values, $sequence);
    }

    public function insertUsing(array $columns, $query)
    {
        $this->guard(fn (WrongSideEdits $g, string $table) => $g->checkInsertFromQuery($table));

        return parent::insertUsing($columns, $query);
    }

    public function insertOrIgnoreUsing(array $columns, $query)
    {
        $this->guard(fn (WrongSideEdits $g, string $table) => $g->checkInsertFromQuery($table));

        return parent::insertOrIgnoreUsing($columns, $query);
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->guardInsert($values);

        return parent::upsert($values, $uniqueBy, $update);
    }

    public function update(array $values)
    {
        $this->guard(fn (WrongSideEdits $g, string $table, ?string $alias) => $g->checkUpdateQuery($this, $table, $alias, $values));

        return parent::update($values);
    }

    public function delete($id = null)
    {
        if (! is_null($id)) {
            $this->where($this->from.'.id', '=', $id);
        }
        $this->guard(fn (WrongSideEdits $g, string $table, ?string $alias) => $g->checkDeleteQuery($this, $table, $alias));
        if ($this->from === 'users') {
            // Users named on requisitions are kept (PD-21 / PD-74). Model deletes
            // come through here too.
            foreach ((clone $this)->reorder()->pluck('id') as $userId) {
                \App\Support\UserRecordGuard::assertMayDelete((int) $userId);
            }
        }

        return parent::delete();
    }

    private function guardInsert(array $values): void
    {
        if ($values === []) {
            return;
        }
        $rows = is_array(reset($values)) ? $values : [$values];
        $this->guard(fn (WrongSideEdits $g, string $table) => $g->checkInsertRows($table, $rows));
    }

    /** Runs $check with the written table (and its alias), when the table is a plain name. */
    private function guard(callable $check): void
    {
        if (! is_string($this->from) || $this->from === '') {
            return;
        }
        $parts = preg_split('/\s+as\s+/i', trim($this->from));
        $check(app(WrongSideEdits::class), $parts[0], $parts[1] ?? null);
    }
}
