<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A service requisition: Shore Repair (equipment sent ashore) or Renewal
 * (certificates renewed). Same origin as an item requisition - raised by the
 * deck officer or the engine room, signed off by Master/Chief Engineer, then
 * GM (SRD) - but it stops at SRD level: there's no SSM/procurement leg,
 * because nothing physical comes back to the ship to be received.
 *
 * vessels/users carry legacy increments() ids (int unsigned), so the keys to
 * them are unsignedInteger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requisitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('vessel_id');

            // Assigned at submit, like an item requisition's - a draft has
            // none, so it's nullable rather than holding a placeholder.
            $table->string('req_no')->nullable()->unique();
            $table->date('req_date');

            $table->string('service_type', 20);   // shore_repair | renewal
            $table->date('due_date')->nullable();
            $table->text('description');

            // 'draft' until submitted, then the same free-text statuses the
            // order chain already uses (RoleController's $approved_by_*),
            // ending at 'approved by srd-general-manager' or 'rejected'.
            $table->string('status')->default('draft');
            $table->boolean('is_submitted')->default(false);

            $table->unsignedInteger('created_by');
            $table->string('created_by_role');

            // Terminal, and open to whoever currently holds it - same rule as
            // an item requisition's rejection.
            $table->unsignedInteger('rejected_by')->nullable();
            $table->string('rejected_by_role')->nullable();
            $table->string('rejected_at_stage')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();

            $table->foreign('vessel_id')->references('id')->on('vessels')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['vessel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requisitions');
    }
};
