<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Good, Fair, Bad, Not Classified. Exactly one is the default for a new component. */
class PmsCondition extends Model
{
    protected $fillable = ['name', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function components()
    {
        return $this->hasMany(PmsComponent::class, 'condition_id');
    }

    public static function defaultId(): ?int
    {
        return static::where('is_default', true)->value('id') ?? static::orderBy('id')->value('id');
    }
}
