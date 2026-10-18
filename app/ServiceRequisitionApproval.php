<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Who has signed off a service requisition - see the table's migration. */
class ServiceRequisitionApproval extends Model
{
    protected $guarded = [];

    public function serviceRequisition()
    {
        return $this->belongsTo(ServiceRequisition::class);
    }
}
