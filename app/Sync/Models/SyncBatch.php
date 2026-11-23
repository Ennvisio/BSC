<?php

namespace App\Sync\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A push batch. The vessel keeps each one it sends (direction out) until the
 * cloud acknowledges it, and sends exactly the same body again on retry. The
 * cloud keeps each one it applied (direction in) with the acknowledgement it
 * gave, so a repeated batch gets the same answer and is not applied twice.
 */
class SyncBatch extends Model
{
    public const OUT = 'out';

    public const IN = 'in';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACKED = 'acked';

    public const STATUS_APPLIED = 'applied';

    protected $table = 'sync_batches';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['seq_from' => 'integer', 'seq_to' => 'integer', 'events' => 'integer', 'bytes' => 'integer', 'attempts' => 'integer', 'acked_at' => 'datetime'];
    }
}
