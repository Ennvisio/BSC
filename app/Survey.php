<?php

namespace App;

use App\VesselSurvey;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    public function vesselSurveys()
    {
    	return $this->hasMany(VesselSurvey::class);
    }
}
