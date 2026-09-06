<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BudgetGroup extends Model
{
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
