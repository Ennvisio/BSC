<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Port extends Model
{
    protected $fillable = ['unlocode', 'country_code', 'country_name', 'name', 'name_ascii'];

    public function getLabelAttribute(): string
    {
        return "{$this->name} ({$this->country_name})";
    }
}
