<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Manufacturer extends Model
{
    protected $fillable = ['name'];

    public function components()
    {
        return $this->hasMany(PmsComponent::class);
    }
}
