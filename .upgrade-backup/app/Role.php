<?php

namespace App;

use App\User;
use App\Vessel;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
     public function user()
    {
       return $this->belongsTo(User::class);
    }
    public function vessel(){
       return $this->belongsTo(Vessel::class);
    }
}
