<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Vessel;
use App\Certificate;
class VesselCertificate extends Model
{
     public function vessel(){
       return $this->belongsTo(Vessel::class);
    }
     public function certificate(){
       return $this->belongsTo(Certificate::class);
    }
}
