<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PmsJobChecklistItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['job_definition_id', 'position', 'text'];

    public function definition()
    {
        return $this->belongsTo(PmsJobDefinition::class, 'job_definition_id');
    }
}
