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
    /**
     * What a requisition number starts with: "JOY/DK" for a vessel whose
     * acronym is JOY, giving JOY/DK/STR/08/2026.
     *
     * A vessel with no acronym yet falls back to the original bare "DK" rather
     * than blocking the officer from submitting - the acronym is required on
     * the vessel form from now on, but ships created before that have none
     * until someone edits them.
     */
    public function reqNoPrefix(): string
    {
        return $this->acronym ? $this->acronym.'/DK' : 'DK';
    }

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
    public function items(){
        return $this->belongsToMany(Item::class, 'vessel_items');
    }
}
