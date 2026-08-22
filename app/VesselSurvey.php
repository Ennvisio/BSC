<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Vessel;
use App\Survey;
class VesselSurvey extends Model
{
    public function vessel(){
       return $this->belongsTo(Vessel::class);
    }
    public function survey(){
       return $this->belongsTo(Survey::class);
    }
}
