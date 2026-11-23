<?php

namespace App\Sync\Models;

use Illuminate\Database\Eloquent\Model;

/** One sync action, for operators. See App\Sync\SyncLogger. */
class SyncLog extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_WARNING = 'warning';

    public const STATUS_ERROR = 'error';

    protected $table = 'sync_logs';

    public $timestamps = false;

    protected $fillable = ['node_id', 'action', 'status', 'events', 'bytes', 'duration_ms', 'message', 'context', 'created_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'events' => 'integer', 'bytes' => 'integer', 'duration_ms' => 'integer'];
    }
}
