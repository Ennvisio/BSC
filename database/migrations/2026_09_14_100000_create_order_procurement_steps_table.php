<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SSM procurement workflow's history: one row per stage as it completes,
 * append-only. Deliberately rows rather than a column per stage - order_approvals
 * is the same idea built as 20+ columns, and every role added there meant a
 * migration plus touching every query. Here a new stage is a new `step` value.
 *
 * orders.procurement_stage holds the CURRENT position (what's awaiting action);
 * this table is everything already done, with who and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_procurement_steps', function (Blueprint $table) {
            $table->bigIncrements('id');
            // orders.id and users.id are legacy increments() - int(10) unsigned,
            // not bigint - so these have to be integer or the FK won't take.
            $table->unsignedInteger('order_id');
            $table->string('step', 40);
            // 'done', or 'skipped' for an Advance Payment that wasn't required.
            $table->string('outcome', 12)->default('done');
            $table->unsignedInteger('completed_by');
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            // Stage-specific fields (tender ref, PO number, awarded vendor) -
            // only about half the stages have any, so real columns would be
            // mostly null.
            $table->json('meta')->nullable();
            $table->timestamps();

            // Forward-only flow: a stage completes exactly once.
            $table->unique(['order_id', 'step']);

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('completed_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_procurement_steps');
    }
};
