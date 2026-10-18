<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Pump, Compressor, Engine... The critical/class-relevant flags are the
 * defaults a new component of this category starts with - the component
 * keeps its own copy, so changing a category later doesn't rewrite existing
 * equipment.
 */
class PmsComponentCategory extends Model
{
    protected $fillable = ['name', 'is_critical', 'is_class_relevant'];

    protected $casts = [
        'is_critical' => 'boolean',
        'is_class_relevant' => 'boolean',
    ];

    public function components()
    {
        return $this->hasMany(PmsComponent::class, 'category_id');
    }
}
