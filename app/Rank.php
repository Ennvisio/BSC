<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** A shipboard rank a PMS job can be made the responsibility of (CE, 2E, CO...). */
class Rank extends Model
{
    const DEPARTMENTS = ['Deck', 'Engine', 'Electrical', 'Catering'];

    protected $fillable = ['code', 'name', 'department', 'sort_order'];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
