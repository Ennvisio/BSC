<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of bringing the approval chain in line with the target workflow:
 * GM (SRD) gets two more delegate targets (DGM/Superintendent SRD, alongside
 * the existing AGM/AM SRD columns), the SSM final-action stage becomes a real
 * 3-way race (AGM/AM/Superintendent SSM - AGM already existed but was dead
 * code), and Technical/Marine Superintendent get a purely informational,
 * never-gating sign-off column each. All nullable, all additive - no existing
 * column's meaning changes, so every in-flight order keeps working exactly
 * as it does today mid-chain. Types match the existing approval columns
 * (`integer`, i.e. signed int(11), not unsignedInteger).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_approvals', function (Blueprint $table) {
            $table->integer('forwarded_to_dgm_srd')->nullable()->after('forwarded_to_am_by_agm_srd');
            $table->integer('dgm_srd_app')->nullable()->after('forwarded_to_dgm_srd');
            $table->integer('forwarded_to_superintendent_srd')->nullable()->after('dgm_srd_app');
            $table->integer('superintendent_srd_app')->nullable()->after('forwarded_to_superintendent_srd');
            $table->integer('superintendent_ssm_app')->nullable()->after('am_app_ssm');
            $table->integer('tech_superintendent_app')->nullable()->after('superintendent_ssm_app');
            $table->integer('marine_superintendent_app')->nullable()->after('tech_superintendent_app');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_approvals', function (Blueprint $table) {
            $table->dropColumn([
                'forwarded_to_dgm_srd', 'dgm_srd_app',
                'forwarded_to_superintendent_srd', 'superintendent_srd_app',
                'superintendent_ssm_app', 'tech_superintendent_app', 'marine_superintendent_app',
            ]);
        });
    }
};
