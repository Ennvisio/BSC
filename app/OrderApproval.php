<?php

namespace App;
use App\Order;
use Illuminate\Database\Eloquent\Model;

class OrderApproval extends Model
{
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
