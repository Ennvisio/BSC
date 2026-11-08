<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockImport extends Model
{
    protected $fillable = [
        'vessel_id', 'category_id', 'uploaded_by', 'filename', 'source', 'status',
        'row_count', 'updated_count', 'skipped_count', 'failed_count', 'error_log',
    ];

    const SOURCE_UPLOAD = 'upload';
    const SOURCE_MANUAL = 'manual';

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
