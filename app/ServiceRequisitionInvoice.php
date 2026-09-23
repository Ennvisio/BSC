<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The invoice header captured at Invoice Verification. Per-line figures live
 * on service_requisition_items; only the invoice-level discount and the frozen
 * payable amount are stored here.
 */
class ServiceRequisitionInvoice extends Model
{
    protected $fillable = [
        'service_requisition_id', 'invoice_no', 'invoice_date', 'discount', 'payable', 'verified_by', 'verified_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'verified_at' => 'datetime',
        'discount' => 'decimal:2',
        'payable' => 'decimal:2',
    ];

    public function serviceRequisition()
    {
        return $this->belongsTo(ServiceRequisition::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * SUM(line_total) - never stored, so it can't drift away from the lines it
     * is the sum of. payable IS stored, because it's the as-billed figure that
     * was actually approved for payment.
     */
    public function subtotal()
    {
        return $this->serviceRequisition ? $this->serviceRequisition->items->sum('line_total') : 0;
    }
}
