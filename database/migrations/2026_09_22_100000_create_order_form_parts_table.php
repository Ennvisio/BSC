<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (requisition, part) of the Ship's Spares Demand and Procurement
 * Approval Form. The questions live in code (App\RequisitionForm); this only
 * holds what was answered, so the same table serves Part A (ship), Part B (SRD)
 * and Part C (SSM) without a column per question.
 *
 * orders.id and users.id are legacy increments() ids (int(10) unsigned), so
 * the foreign keys to them are unsignedInteger, not unsignedBigInteger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_form_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_id');
            $table->char('part', 1);

            // Which definition of the questions these answers were given
            // against - so rewording a question later never rewrites history.
            $table->unsignedSmallInteger('form_version')->default(1);

            $table->json('answers');

            // Set only once every required answer AND the declaration are in.
            // A part can exist without it: Save & Back keeps a half-finished
            // form, but submission (and later, approval) checks for this.
            $table->timestamp('completed_at')->nullable();

            // {declared_by, declared_by_role, declared_at} - the declaration
            // as it stood when it was made, not a live lookup of the user.
            $table->json('declaration')->nullable();

            $table->unsignedInteger('filled_by')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('filled_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['order_id', 'part']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_form_parts');
    }
};
