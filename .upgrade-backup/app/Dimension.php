<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Dimension extends Model
{
    public function vessel(){
        return $this->belongsTo('App\Vessel');
    }
}
