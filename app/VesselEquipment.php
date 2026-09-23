<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of a vessel's Equipment & Maker List - what a Shore Repair service
 * requisition line points at (see ServiceRequisitionController).
 */
class VesselEquipment extends Model
{
    protected $fillable = ['vessel_id', 'name', 'maker', 'status', 'created_by', 'updated_by'];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
