<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VesselParticular extends Model
{
    public function vessel(){
        return $this->belongsTo('App\Vessel');
    }
}
