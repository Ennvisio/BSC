<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Where on board a component sits (ENGINE, DECK...) - the top grouping of the component tree. */
class PmsLocation extends Model
{
    protected $fillable = ['name', 'sort_order'];

    public function components()
    {
        return $this->hasMany(PmsComponent::class, 'location_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
