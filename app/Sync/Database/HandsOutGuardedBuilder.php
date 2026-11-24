<?php

namespace App\Sync\Database;

/** The ownership-guarded query builder (PD-34) in place of the plain one. */
trait HandsOutGuardedBuilder
{
    public function query()
    {
        return new OwnershipGuardedBuilder($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }
}
