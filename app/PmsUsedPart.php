<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Stock taken for a PMS job when it was completed. The movement itself is the stock_consumptions row. */
class PmsUsedPart extends Model
{
    protected $fillable = ['job_id', 'item_id', 'stock_consumption_id', 'quantity'];

    public function job()
    {
        return $this->belongsTo(PmsJob::class, 'job_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function consumption()
    {
        return $this->belongsTo(StockConsumption::class, 'stock_consumption_id');
    }
}
