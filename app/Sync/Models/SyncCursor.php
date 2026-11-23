<?php

namespace App\Sync\Models;

use Illuminate\Database\Eloquent\Model;

/** How far a peer node has got through one stream of events. See App\Sync\SyncCursors. */
class SyncCursor extends Model
{
    protected $table = 'sync_cursors';

    protected $fillable = ['node_id', 'stream', 'position', 'last_event_id'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
