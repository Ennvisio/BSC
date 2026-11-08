<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of the stock ledger - see the create_stock_movements_table
 * migration for what each type means and how qty/balance_after behave for
 * it. Always written by App\Services\StockService, never directly - that's
 * what keeps the ledger and vessel_items.stock_qty from ever disagreeing.
 */
class StockMovement extends Model
{
    const TYPE_OPENING = 'opening';
    const TYPE_RECEIPT = 'receipt';
    const TYPE_CONSUMPTION = 'consumption';
    const TYPE_ADJUSTMENT = 'adjustment';

    const TYPES = [
        self::TYPE_OPENING => 'Opening Balance',
        self::TYPE_RECEIPT => 'Receipt',
        self::TYPE_CONSUMPTION => 'Consumption',
        self::TYPE_ADJUSTMENT => 'Adjustment',
    ];

    protected $fillable = [
        'vessel_id', 'item_id', 'type', 'qty', 'balance_after', 'moved_on',
        'order_id', 'order_item_id', 'stock_consumption_id', 'stock_import_id',
        'remarks', 'user_id',
    ];

    protected $casts = [
        'moved_on' => 'date',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function stockConsumption()
    {
        return $this->belongsTo(StockConsumption::class);
    }

    public function stockImport()
    {
        return $this->belongsTo(StockImport::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
