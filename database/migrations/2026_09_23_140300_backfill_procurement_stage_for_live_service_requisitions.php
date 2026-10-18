<?php

use App\ServiceProcurementStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Service requisitions that were already out with an SRD delegate when the
 * procurement workflow was added have no procurement_stage, so they sit in the
 * old "delegate reviews and sends it back to GM" path - which the UI no longer
 * offers. This puts the in-flight ones onto the workflow.
 *
 * Only ones the delegate has NOT yet reviewed are moved. A requisition already
 * reviewed and back with GM is left alone: GM still chooses there, and
 * delegating again is what starts procurement for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_requisitions')
            ->join('service_requisition_approvals', 'service_requisition_approvals.service_requisition_id', '=', 'service_requisitions.id')
            ->whereNull('service_requisitions.procurement_stage')
            ->where('service_requisitions.is_submitted', true)
            ->where('service_requisitions.status', '!=', 'rejected')
            ->whereNull('service_requisition_approvals.gm_app')
            ->whereNotNull('service_requisition_approvals.assigned_to_srd')
            // Delegated but not yet signed off by any of the four.
            ->whereNull('service_requisition_approvals.dgm_srd_app')
            ->whereNull('service_requisition_approvals.agm_app')
            ->whereNull('service_requisition_approvals.ast_m_app')
            ->whereNull('service_requisition_approvals.superintendent_srd_app')
            ->update(['service_requisitions.procurement_stage' => ServiceProcurementStage::ADMINISTRATIVE_APPROVAL]);
    }

    public function down(): void
    {
        // Only unwinds rows with no recorded steps - anything already worked
        // through the new workflow keeps its stage, since dropping it would
        // strand completed history against a requisition that claims never to
        // have entered procurement.
        DB::table('service_requisitions')
            ->where('procurement_stage', ServiceProcurementStage::ADMINISTRATIVE_APPROVAL)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('service_requisition_procurement_steps')
                    ->whereColumn('service_requisition_procurement_steps.service_requisition_id', 'service_requisitions.id');
            })
            ->update(['procurement_stage' => null]);
    }
};
