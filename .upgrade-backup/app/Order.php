<?php

namespace App;

use App\Category;
use App\OrderItem;
use App\Vessel;
use App\OrderApproval;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
	public function vessel(){
		return $this->belongsTo(Vessel::class);
	}
	public function orderItems(){
		return $this->hasMany(OrderItem::class);
	}
	public function category(){
		return $this->belongsTo(Category::class);
	}
	public function orderApproval()
    {
        return $this->hasOne(OrderApproval::class);
    }
}
