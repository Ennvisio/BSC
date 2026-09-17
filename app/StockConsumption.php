<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One consumption record: an officer stating that some quantity of an item
 * left usable stock, and why. The actual stock deduction happens in
 * StockService::consume() - this row is the record of it, not the source of
 * truth for the running balance (that's still vessel_items.stock_qty).
 *
 * No approval chain - unlike a requisition, this is a statement of something
 * that already happened, not a request. It takes effect the moment it's
 * saved (see StockConsumptionController::store()).
 */
class StockConsumption extends Model
{
    protected $fillable = [
        'vessel_id', 'item_id', 'order_id', 'consumption_type',
        'qty', 'consumed_on', 'department', 'purpose', 'remarks', 'recorded_by',
    ];

    protected $casts = [
        'consumed_on' => 'date',
    ];

    const TYPE_USED = 'used';
    const TYPE_DAMAGED = 'damaged';
    const TYPE_EXPIRED = 'expired';
    const TYPE_LOST = 'lost';
    const TYPE_OTHER = 'other';

    const TYPES = [
        self::TYPE_USED => 'Used',
        self::TYPE_DAMAGED => 'Damaged',
        self::TYPE_EXPIRED => 'Expired',
        self::TYPE_LOST => 'Lost',
        self::TYPE_OTHER => 'Other',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /** The requisition this item was originally brought on board for, if the officer linked one. */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Photos, defect reports, expiry labels - whatever backs up the entry. */
    public function attachments()
    {
        return $this->belongsToMany(Attachment::class, 'attachment_stock_consumption')
            ->withTimestamps()
            ->orderBy('attachment_stock_consumption.created_at');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->consumption_type] ?? ucfirst($this->consumption_type);
    }
}
