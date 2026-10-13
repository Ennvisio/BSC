<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The invoice header captured at Invoice Verification. Per-line prices live
 * on order_items when they were entered, but subtotal/discount/payable are
 * the frozen as-billed figures that were actually verified - see the
 * add_subtotal_to_order_invoices_table migration for why subtotal is its own
 * entered column rather than SUM(line_total).
 */
class OrderInvoice extends Model
{
    protected $fillable = [
        'order_id', 'invoice_no', 'invoice_date', 'currency_code', 'subtotal', 'discount', 'payable', 'verified_by', 'verified_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'verified_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'payable' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
