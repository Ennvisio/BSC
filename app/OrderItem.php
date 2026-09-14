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
	public function attachments(){
		return $this->belongsToMany(Attachment::class, 'attachment_order_item')->orderBy('attachment_order_item.created_at');
	}
}
