<?php

namespace App\Sync\Models;

use Illuminate\Database\Eloquent\Model;

/** A conflict between a received change and a local one. See App\Sync\Engine\ChangeApplier. */
class SyncConflict extends Model
{
    public const KIND_UPDATE_UPDATE = 'update_update';   // both changed the row

    public const KIND_UPDATE_DELETE = 'update_delete';   // remote changed it, local deleted it

    public const KIND_DELETE_UPDATE = 'delete_update';   // remote deleted it, local changed it

    public const KIND_DUPLICATE_KEY = 'duplicate_key';   // same natural key, different UUID

    public const KIND_STOCK_MISMATCH = 'stock_mismatch'; // a stock movement does not add up to the balance here

    public const KIND_REFUSED = 'refused';               // the sender may not make this change (ownership)

    public const KEPT_LOCAL = 'kept_local';

    public const TOOK_REMOTE = 'took_remote';

    public const NEEDS_REVIEW = 'needs_review';

    public const MERGED = 'merged';                      // some columns from each side

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    protected $table = 'sync_conflicts';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** Password hashes and the like are never kept in a conflict (Checkpoint 7). */
    protected static function booted(): void
    {
        static::saving(function (self $conflict) {
            $conflict->local_values = \App\Sync\SensitiveValues::mask((string) $conflict->table_name, $conflict->local_values);
            $conflict->remote_values = \App\Sync\SensitiveValues::mask((string) $conflict->table_name, $conflict->remote_values);
        });
    }

    protected function casts(): array
    {
        return ['local_values' => 'array', 'remote_values' => 'array', 'local_version' => 'integer', 'remote_version' => 'integer', 'reviewed_at' => 'datetime'];
    }
}
