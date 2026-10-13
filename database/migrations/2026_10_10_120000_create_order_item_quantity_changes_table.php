<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An append-only log of corrections to an order line's quantity, made after
 * the ship raised it - by an SRD reviewer (Req Qty) or SSM at Delivery
 * (Deliver Qty). The column itself only ever holds the current figure, so
 * without this there would be no way to show "20 -> 18 by Rasel" once the
 * requisition has moved on - this is what view-order-detail's strikethrough
 * diff reads from.
 *
 * changed_by_name/changed_by_role are stamped as they stood at the time,
 * the same reasoning OrderFormPart's declaration carries - who gets credited
 * for a past change must survive a later role transfer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_quantity_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_item_id');

            // Which OrderItem column this correction applied to -
            // 'item_qty' (Req Qty, corrected by SRD) or 'del_item_qty'
            // (Deliver Qty, corrected by SSM).
            $table->string('field');

            $table->integer('old_qty');
            $table->integer('new_qty');

            $table->unsignedInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->string('changed_by_role')->nullable();

            $table->timestamps();

            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['order_item_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_quantity_changes');
    }
};
