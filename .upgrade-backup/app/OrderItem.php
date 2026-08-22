<?php

namespace App;

use App\Item;
use App\Order;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
	public function order(){
		return $this->belongsTo(Order::class);
	}
	public function item(){
		return $this->belongsTo(Item::class);
	}
}
