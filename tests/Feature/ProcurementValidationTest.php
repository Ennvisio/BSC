<?php

namespace Tests\Feature;

use App\OrderInvoice;
use App\ProcurementStage;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/**
 * Figures checked (or flagged) at Invoice Verification and Payment - see
 * ProcurementController::validateInvoice()/paymentMismatchWarning().
 */
class ProcurementValidationTest extends TestCase
{
    use BuildsRequisitionSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
    }

    public function test_invoice_qty_cannot_exceed_received_qty(): void
    {
        $ssm = $this->makeUser('am-ssm');
        $order = $this->makeOrder([
            'procurement_stage' => ProcurementStage::INVOICE_VERIFICATION,
            'approval' => ['dgm_app_ssm' => 1, 'assigned_to_ssm' => $ssm->id],
        ]);
        $item = $this->makeItem($order->category_id);
        $line = $this->makeOrderItem($order->id, $item->id, 10, ['rcv_item_qty' => 5]);

        $response = $this->actingAs($ssm)->postJson('/procurement/'.$order->id.'/complete', [
            'currency_code' => 'BDT',
            'subtotal' => 100,
            'unit_price' => [$line->id => '10'],
            'invoice_qty' => [$line->id => '8'], // more than the 5 actually received
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('more than the received quantity', $response->json('message'));

        $this->assertSame(ProcurementStage::INVOICE_VERIFICATION, $order->fresh()->procurement_stage, 'the stage must not have advanced');
        $this->assertNull($line->fresh()->invoice_qty, 'nothing should have been saved');
    }

    public function test_invoice_qty_within_received_qty_is_accepted(): void
    {
        $ssm = $this->makeUser('am-ssm');
        $order = $this->makeOrder([
            'procurement_stage' => ProcurementStage::INVOICE_VERIFICATION,
            'approval' => ['dgm_app_ssm' => 1, 'assigned_to_ssm' => $ssm->id],
        ]);
        $item = $this->makeItem($order->category_id);
        $line = $this->makeOrderItem($order->id, $item->id, 10, ['rcv_item_qty' => 5]);

        $this->actingAs($ssm)->postJson('/procurement/'.$order->id.'/complete', [
            'currency_code' => 'BDT',
            'subtotal' => 50,
            'unit_price' => [$line->id => '10'],
            'invoice_qty' => [$line->id => '5'],
        ])->assertOk();

        $this->assertSame(ProcurementStage::FINANCE_CLEARANCE, $order->fresh()->procurement_stage);
        $this->assertSame(5, $line->fresh()->invoice_qty);
    }

    public function test_payment_warns_but_does_not_block_when_paid_amount_differs_from_invoice_total(): void
    {
        $ssm = $this->makeUser('am-ssm');
        $order = $this->makeOrder([
            'procurement_stage' => ProcurementStage::PAYMENT,
            'approval' => ['dgm_app_ssm' => 1, 'assigned_to_ssm' => $ssm->id],
        ]);
        OrderInvoice::create([
            'order_id' => $order->id, 'subtotal' => 100, 'discount' => 0, 'payable' => 100,
            'verified_by' => $ssm->id, 'currency_code' => 'BDT',
        ]);

        $response = $this->actingAs($ssm)->postJson('/procurement/'.$order->id.'/complete', [
            'paid_amount' => 80,
            'payment_ref' => 'TXN-1',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('differs from the invoice total', $response->json(0));
        $this->assertSame(ProcurementStage::CLOSED, $order->fresh()->procurement_stage, 'a mismatch is a warning, not a block - the stage still advances');
    }

    public function test_payment_matching_the_invoice_total_has_no_warning(): void
    {
        $ssm = $this->makeUser('am-ssm');
        $order = $this->makeOrder([
            'procurement_stage' => ProcurementStage::PAYMENT,
            'approval' => ['dgm_app_ssm' => 1, 'assigned_to_ssm' => $ssm->id],
        ]);
        OrderInvoice::create([
            'order_id' => $order->id, 'subtotal' => 100, 'discount' => 0, 'payable' => 100,
            'verified_by' => $ssm->id, 'currency_code' => 'BDT',
        ]);

        $response = $this->actingAs($ssm)->postJson('/procurement/'.$order->id.'/complete', [
            'paid_amount' => 100,
            'payment_ref' => 'TXN-2',
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('differs', $response->json(0));
    }
}
