<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of a service requisition. title/subtitle are copied from the
 * equipment or certificate at the time it was raised - see the table's own
 * migration for why they aren't read back through the relation.
 */
class ServiceRequisitionItem extends Model
{
    protected $fillable = [
        'service_requisition_id', 'vessel_equipment_id', 'vessel_certificate_id',
        'title', 'subtitle', 'quantity',
        // Filled in at Invoice Verification, not when the line is raised.
        'invoice_qty', 'unit_price', 'line_total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function serviceRequisition()
    {
        return $this->belongsTo(ServiceRequisition::class);
    }

    public function equipment()
    {
        return $this->belongsTo(VesselEquipment::class, 'vessel_equipment_id');
    }

    public function certificate()
    {
        return $this->belongsTo(VesselCertificate::class, 'vessel_certificate_id');
    }
}
