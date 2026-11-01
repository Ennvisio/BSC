<?php

namespace Tests\Feature;

use App\Order;
use App\ProcurementStage;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/**
 * Who may approve a requisition, and who may open its detail page - both are
 * "whoever currently holds it", never by role alone. See
 * RoleController::approveRequisition() and HomeController::viewOrderDetail().
 */
class RequisitionApprovalAuthorizationTest extends TestCase
{
    use BuildsRequisitionSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
    }

    // ---------------------------------------------------------------- approve

    public function test_master_of_another_vessel_cannot_approve(): void
    {
        $otherVesselId = $this->makeVessel('MT OTHER VESSEL')->id;

        // Chief Officer has already signed off - the next action is Master's,
        // but only this order's OWN vessel's master.
        $order = $this->makeOrder(['vessel_id' => $this->vesselId, 'approval' => ['cheif_ofcr_app' => 1]]);

        $masterElsewhere = $this->makeUser('master', $otherVesselId);

        $this->actingAs($masterElsewhere)->postJson('/order/approve', ['id' => $order->id])
            ->assertStatus(403);

        $this->assertNull($order->fresh()->orderApproval->master_app, 'master_app must stay untouched');
    }

    public function test_gm_srd_cannot_approve_before_ship_approval(): void
    {
        // Fresh order: nobody has approved anything yet.
        $order = $this->makeOrder();

        $gm = $this->makeUser('gm-srd');

        $this->actingAs($gm)->postJson('/order/approve', ['id' => $order->id])
            ->assertStatus(403);

        $this->assertNull($order->fresh()->orderApproval->gm_app);
    }

    public function test_am_ssm_cannot_approve_before_delivery_stage(): void
    {
        $amSsm = $this->makeUser('am-ssm');

        // In procurement, but still at Tendering - nowhere near Delivery.
        $order = $this->makeOrder([
            'procurement_stage' => ProcurementStage::TENDERING,
            'approval' => ['dgm_app_ssm' => 1, 'assigned_to_ssm' => $amSsm->id],
        ]);

        $this->actingAs($amSsm)->postJson('/order/approve', ['id' => $order->id])
            ->assertStatus(403)
            ->assertJsonFragment(['message' => 'This requisition is not at the Delivery stage yet.']);

        $this->assertSame(ProcurementStage::TENDERING, $order->fresh()->procurement_stage);
    }

    /** The happy path still works - a sibling test to the three refusals above, so a too-broad guard would show up here instead of just in production. */
    public function test_master_of_the_right_vessel_can_approve(): void
    {
        $order = $this->makeOrder(['vessel_id' => $this->vesselId, 'approval' => ['cheif_ofcr_app' => 1]]);
        $master = $this->makeUser('master', $this->vesselId);

        $this->actingAs($master)->postJson('/order/approve', ['id' => $order->id])->assertOk();

        $this->assertNotNull($order->fresh()->orderApproval->master_app);
    }

    // ---------------------------------------------------------------- detail page

    public function test_ship_user_cannot_view_another_vessels_order_detail(): void
    {
        $otherVesselId = $this->makeVessel('MT OTHER VESSEL')->id;
        $order = $this->makeOrder(['vessel_id' => $otherVesselId]);

        $officer = $this->makeUser('chief-officer', $this->vesselId);

        $this->actingAs($officer)->get('/order/detail/'.$order->id)->assertStatus(403);
    }

    public function test_ship_user_can_view_their_own_vessels_order_detail(): void
    {
        $order = $this->makeOrder(['vessel_id' => $this->vesselId]);
        $officer = $this->makeUser('chief-officer', $this->vesselId);

        $this->actingAs($officer)->get('/order/detail/'.$order->id)->assertOk();
    }

    public function test_a_shore_role_can_view_any_vessels_order_detail(): void
    {
        $order = $this->makeOrder(['vessel_id' => $this->vesselId]);
        $gm = $this->makeUser('gm-srd');

        $this->actingAs($gm)->get('/order/detail/'.$order->id)->assertOk();
    }
}
