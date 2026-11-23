<?php

namespace App\Sync\Models;

use Illuminate\Database\Eloquent\Model;

/** One change received from another node. Written through App\Sync\SyncInbox. */
class SyncInboxEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_DEFERRED = 'deferred';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'sync_inbox';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['row_version' => 'integer', 'vessel_id' => 'integer', 'payload' => 'array', 'attempts' => 'integer', 'applied_at' => 'datetime'];
    }
}
