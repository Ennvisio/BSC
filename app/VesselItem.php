<?php

namespace App;

use App\Support\AppMode;
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

    /**
     * Your decision of 2026-11-23: the office may not delete the stock link
     * of a vessel that runs its own server. The link carries that vessel's
     * stock, and the vessel refuses such a delete at sync (PD-34, vessel
     * side), so the two sides would differ until a person reviewed the flag.
     *
     * No screen deletes a link today; the trash refuses to delete an item
     * or a vessel while a link refers to it (PD-60). A delete through the
     * model is refused on the cloud by the general PD-34 guard
     * (App\Sync\VesselOwnership, checked for every Eloquent delete), as for
     * every other vessel-owned record. The vessel's own server is not
     * affected.
     */
    /** Whether the cloud must leave this vessel's stock links alone. */
    public static function lockedOnCloud(int $vesselId): bool
    {
        $mode = app(AppMode::class);

        return $mode->isCloud()
            && config('navisuite.cloud.lock_offline_vessels', true)
            && $mode->vesselIsOfflineEnabled($vesselId);
    }

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
