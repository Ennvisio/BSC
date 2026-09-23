<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One line of a service requisition - a piece of equipment going ashore, or a
 * certificate being renewed. Exactly one of the two ids is set, decided by
 * the parent's service_type.
 *
 * The title/maker/category are COPIED here rather than read back through the
 * relation: a requisition is a document of what was asked for at the time,
 * and the vessel's equipment list and certificates both get edited later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_requisition_id');

            // vessel_equipment is a modern bigint table; vessel_certificates
            // is a legacy increments() one (int unsigned) - the key types have
            // to match each target exactly or MySQL rejects the constraint.
            $table->unsignedBigInteger('vessel_equipment_id')->nullable();
            $table->unsignedInteger('vessel_certificate_id')->nullable();

            $table->string('title');
            $table->string('subtitle')->nullable();   // maker, or certificate category
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->foreign('service_requisition_id')->references('id')->on('service_requisitions')->onDelete('cascade');
            $table->foreign('vessel_equipment_id')->references('id')->on('vessel_equipment')->onDelete('set null');
            $table->foreign('vessel_certificate_id')->references('id')->on('vessel_certificates')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requisition_items');
    }
};
