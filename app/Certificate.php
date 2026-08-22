<?php

namespace App;

use App\VesselCertificate;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    
     public function vesselCertificates(){ 
        return $this->hasMany(VesselCertificate::class);
    }
}
