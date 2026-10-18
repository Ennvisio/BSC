<?php

use App\ProcurementStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Requisitions that were already sitting with an SSM officer when the
 * procurement workflow was added have no procurement_stage, so they fall back
 * to the old approve-once behaviour and never enter the twelve stages. This
 * puts the in-flight ones onto the workflow.
 *
 * Already-'received' requisitions are deliberately left alone. They closed
 * under the old rules, and moving them to Invoice Verification would reopen
 * work everyone considers finished - with no invoice to enter against it.
 * They keep procurement_stage null, which every query still reads as closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Assigned to an SSM officer, final action not yet taken: put them at
        // the start of procurement.
        DB::table('orders')
            ->join('order_approvals', 'order_approvals.order_id', '=', 'orders.id')
            ->whereNull('orders.procurement_stage')
            ->where('orders.ord_status', true)
            ->whereNotNull('order_approvals.dgm_app_ssm')
            ->whereNotIn('orders.status', ['delivered', 'received'])
            ->whereNull('order_approvals.agm_app_ssm')
            ->whereNull('order_approvals.am_app_ssm')
            ->whereNull('order_approvals.superintendent_ssm_app')
            ->update(['orders.procurement_stage' => ProcurementStage::ADMINISTRATIVE_APPROVAL]);

        // Delivered but not yet confirmed by the vessel: that IS the Receipt &
        // Verification stage, so they carry straight on from where they are.
        DB::table('orders')
            ->join('order_approvals', 'order_approvals.order_id', '=', 'orders.id')
            ->whereNull('orders.procurement_stage')
            ->where('orders.ord_status', true)
            ->whereNotNull('order_approvals.dgm_app_ssm')
            ->where('orders.status', 'delivered')
            ->update(['orders.procurement_stage' => ProcurementStage::RECEIPT_VERIFICATION]);
    }

    public function down(): void
    {
        // Only unwinds rows that have no recorded steps - anything already
        // worked through the new workflow keeps its stage, since dropping it
        // would strand completed history against a requisition that claims
        // never to have entered procurement.
        DB::table('orders')
            ->whereIn('procurement_stage', [
                ProcurementStage::ADMINISTRATIVE_APPROVAL,
                ProcurementStage::RECEIPT_VERIFICATION,
            ])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('order_procurement_steps')
                    ->whereColumn('order_procurement_steps.order_id', 'orders.id');
            })
            ->update(['procurement_stage' => null]);
    }
};
