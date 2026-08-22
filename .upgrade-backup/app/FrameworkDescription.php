<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FrameworkDescription extends Model
{
    public function vessel(){
        return $this->belongsTo('App\Vessel');
    }
}
