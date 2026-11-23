<?php

namespace App\Sync\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One captured local change. Rows are written only by the database triggers
 * (App\Sync\TriggerBuilder), never by application code.
 */
class SyncOutboxEvent extends Model
{
    protected $table = 'sync_outbox';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['row_version' => 'integer', 'vessel_id' => 'integer'];
    }
}
