<?php

namespace App\Sync\Engine;

/**
 * A received row has the same natural key (for example a currency code) as
 * a different row here. Not applied; recorded as a conflict for review.
 */
class DuplicateNaturalKey extends InvalidChange
{
    public function __construct(
        public readonly string $table,
        public readonly array $key,
        public readonly object $existing,
        public readonly array $event,
        public readonly string $source,
        public readonly string $side,
    ) {
        parent::__construct("{$table} already has a different row with the same ".implode(', ', $key).'.');
    }
}
