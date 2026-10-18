<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** CHECK (CH), MAINTENANCE (MA), OVERHAUL (O)... */
class PmsJobType extends Model
{
    protected $fillable = ['name', 'abbreviation'];
}
