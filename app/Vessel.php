<?php

namespace App;

use App\VesselCertificate;
use App\Order;
use App\Role;
use App\Survey;
use App\VesselParticular;
use App\VesselSurvey;
use Illuminate\Database\Eloquent\Model;

class Vessel extends Model
{
    public function vesselDetail(){
        return $this->hasOne('App\VesselParticular');
    }

    public function vesselFrameworkAndDetail(){
        return $this->hasOne('App\FrameworkDescription'); 
    }

    public function vesselDimension(){
        return $this->hasOne('App\Dimension');
    }

    public function vesselEngine(){
        return $this->hasOne('App\Engine');
    }

    public function vesselBoiler(){
        return $this->hasOne('App\Boiler');
    }
    public function orders(){
        return $this->hasMany(Order::class);
    }
    public function roles(){
        return $this->hasMany(Role::class);
    }
    public function vesselSurveys(){ 
        return $this->hasMany(VesselSurvey::class);
    }
    public function vesselCertificates(){ 
        return $this->hasMany(VesselCertificate::class);
    }
}
