<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parts a PMS job needs. One table holds two kinds of row: the definition's
 * template (job_definition_id set) and the job's own copy (job_id set, made
 * when the job is generated), so a job's parts can be edited without
 * touching the template. item_id / order_id are unsignedInteger like the legacy int
 * unsigned items.id / orders.id they point at; item_id is null for a custom
 * part that isn't in the catalog (it can't be requisitioned until it is).
 *
 * pms_used_parts is what was actually taken from stock when a job was
 * completed; the stock movement itself is the stock_consumptions row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_required_parts', function (Blueprint $t) {
            $t->id();
            // Exactly one is set: the definition for a template row, the job
            // for the copy made when that job was generated.
            $t->uuid('job_definition_id')->nullable();
            $t->uuid('job_id')->nullable();
            $t->unsignedInteger('item_id')->nullable();
            $t->string('part_name');
            $t->string('article_no')->nullable();
            $t->string('part_no')->nullable();
            $t->string('drawing_no')->nullable();
            $t->foreignId('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $t->unsignedInteger('quantity');
            $t->string('unit');
            $t->unsignedSmallInteger('est_delivery_weeks')->nullable();
            $t->decimal('est_price', 12, 2)->nullable();
            $t->unsignedInteger('order_id')->nullable();
            $t->timestamps();

            $t->foreign('job_definition_id')->references('id')->on('pms_job_definitions')->cascadeOnDelete();
            $t->foreign('job_id')->references('id')->on('pms_jobs')->cascadeOnDelete();
            $t->foreign('item_id')->references('id')->on('items')->nullOnDelete();
            $t->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });

        Schema::create('pms_used_parts', function (Blueprint $t) {
            $t->id();
            $t->uuid('job_id');
            $t->unsignedInteger('item_id');
            $t->unsignedBigInteger('stock_consumption_id');
            $t->unsignedInteger('quantity');
            $t->timestamps();

            $t->foreign('job_id')->references('id')->on('pms_jobs')->cascadeOnDelete();
            $t->foreign('item_id')->references('id')->on('items');
            $t->foreign('stock_consumption_id')->references('id')->on('stock_consumptions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_used_parts');
        Schema::dropIfExists('pms_required_parts');
    }
};
