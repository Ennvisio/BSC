<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** One running-hour meter reading - the total on the meter that day, not hours since the last one. */
class PmsCounterReading extends Model
{
    protected $fillable = ['component_id', 'reading_date', 'value', 'entered_by'];

    protected $casts = [
        'reading_date' => 'date',
        'value' => 'decimal:1',
    ];

    public function component()
    {
        return $this->belongsTo(PmsComponent::class, 'component_id');
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
