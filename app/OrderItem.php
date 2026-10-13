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

	/** Every correction logged against this line, oldest first. */
	public function quantityChanges()
	{
		return $this->hasMany(OrderItemQuantityChange::class)->orderBy('created_at');
	}

	/**
	 * This line's correction history for one column ('item_qty' or
	 * 'del_item_qty'), oldest first - what the strikethrough diff on the
	 * detail page is built from. A $loaded quantityChanges relation (eager-
	 * loaded for the whole order) is filtered in memory rather than queried
	 * again per line.
	 */
	public function quantityChangesFor(string $field)
	{
		$changes = $this->relationLoaded('quantityChanges') ? $this->quantityChanges : $this->quantityChanges()->get();

		return $changes->where('field', $field)->values();
	}
}
