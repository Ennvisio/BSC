<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ItemImport extends Model
{
    protected $fillable = [
        'vessel_id', 'uploaded_by', 'filename', 'status',
        'row_count', 'imported_count', 'failed_count', 'error_log',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
