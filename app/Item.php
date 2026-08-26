<?php

namespace App;

use App\Category;
use App\OrderItem;
use Illuminate\Database\Eloquent\Model; 
class Item extends Model
{
	public function category()
	{
		return $this->belongsTo(Category::class);
	}
	public function orderItems(){
		return $this->hasMany(OrderItem::class);
	}
	public function itemGroup(){
		return $this->belongsTo(ItemGroup::class);
	}
	public function vessels(){
		return $this->belongsToMany(Vessel::class, 'vessel_items');
	}
}
