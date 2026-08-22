<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Boiler extends Model
{
    public function vessel(){
        return $this->belongsTo('App\Boiler');
    }
}
