<?php

namespace App;

use App\Item;
use App\Order;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
	public function items()
	{
		return $this->hasMany(Item::class);
	}
	public function order(){
		return $this->hasOne(Order::class);
	}
}
