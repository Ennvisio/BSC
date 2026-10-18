<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockImport extends Model
{
    protected $fillable = [
        'vessel_id', 'category_id', 'uploaded_by', 'filename', 'status',
        'row_count', 'updated_count', 'skipped_count', 'failed_count', 'error_log',
    ];

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
