<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The vessel_items pivot, as a real model - it stopped being a plain join
 * table the moment it started carrying per-vessel stock (see StockService).
 */
class VesselItem extends Model
{
    protected $table = 'vessel_items';

    protected $casts = [
        'last_supply_date' => 'date',
        'stock_updated_at' => 'datetime',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function stockUpdatedBy()
    {
        return $this->belongsTo(User::class, 'stock_updated_by');
    }

    /** Below the reorder threshold the Master set (no threshold = never low). */
    public function isLowStock(): bool
    {
        return $this->min_qty !== null && $this->stock_qty <= $this->min_qty;
    }
}
