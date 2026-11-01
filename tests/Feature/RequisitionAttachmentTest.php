<?php

namespace Tests\Feature;

use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/**
 * Attachments stay personal and vessel-scoped: "Choose from my files" never
 * shows anyone else's uploads, attaching never pulls in a file the caller
 * didn't upload themselves, and the per-item attachment list is read-only
 * for anyone outside the order's own vessel (same rule as viewing the order
 * itself - see RequisitionApprovalAuthorizationTest). Also covers storeStep2
 * re-linking attachments across its delete-and-recreate of the line set.
 */
class RequisitionAttachmentTest extends TestCase
{
    use BuildsRequisitionSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
    }

    // ---------------------------------------------------------------- my files

    public function test_my_files_list_is_scoped_to_the_current_user(): void
    {
        $owner = $this->makeUser('chief-officer', $this->vesselId);
        $this->makeAttachment($owner->id);

        $otherVesselId = $this->makeVessel('MT OTHER VESSEL')->id;
        $otherUser = $this->makeUser('chief-officer', $otherVesselId);

        $this->actingAs($otherUser)->getJson('/attachments/my-files')
            ->assertOk()
            ->assertJsonCount(0);

        $this->actingAs($owner)->getJson('/attachments/my-files')
            ->assertOk()
            ->assertJsonCount(1);
    }

    // ---------------------------------------------------------------- attach

    public function test_cannot_attach_to_another_vessels_draft(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);

        $otherVesselId = $this->makeVessel('MT OTHER VESSEL')->id;
        $order = $this->makeOrder(['vessel_id' => $otherVesselId, 'status' => 'draft', 'category_id' => $category->id]);
        $this->makeOrderItem($order->id, $item->id, 5);

        $officer = $this->makeUser('chief-officer', $this->vesselId);
        $file = $this->makeAttachment($officer->id);

        $this->actingAs($officer)
            ->postJson("/requisition/{$order->id}/items/{$item->id}/attachments/attach", [
                'attachment_ids' => [$file->id],
            ])
            ->assertStatus(403);
    }

    public function test_cannot_attach_someone_elses_file(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $order = $this->makeOrder(['vessel_id' => $this->vesselId, 'status' => 'draft', 'category_id' => $category->id]);
        $this->makeOrderItem($order->id, $item->id, 5);

        $officer = $this->makeUser('chief-officer', $this->vesselId);
        $someoneElse = $this->makeUser('chief-officer', $this->vesselId, null, 'someone-else@test.local');
        $notMine = $this->makeAttachment($someoneElse->id);

        // Not refused outright - attach() just filters the request down to
        // the caller's own files, same as "my files" only ever listing
        // their own uploads in the first place.
        $response = $this->actingAs($officer)
            ->postJson("/requisition/{$order->id}/items/{$item->id}/attachments/attach", [
                'attachment_ids' => [$notMine->id],
            ])
            ->assertOk();

        $this->assertSame([], $response->json());
    }

    // ---------------------------------------------------------------- forItem (listing)

    public function test_ship_user_cannot_list_another_vessels_item_attachments(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);

        $otherVesselId = $this->makeVessel('MT OTHER VESSEL')->id;
        $order = $this->makeOrder(['vessel_id' => $otherVesselId, 'category_id' => $category->id]);
        $this->makeOrderItem($order->id, $item->id, 5);

        $officer = $this->makeUser('chief-officer', $this->vesselId);

        $this->actingAs($officer)
            ->getJson("/requisition/{$order->id}/items/{$item->id}/attachments")
            ->assertStatus(403);
    }

    public function test_ship_user_can_list_their_own_vessels_item_attachments(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $order = $this->makeOrder(['vessel_id' => $this->vesselId, 'category_id' => $category->id]);
        $this->makeOrderItem($order->id, $item->id, 5);

        $officer = $this->makeUser('chief-officer', $this->vesselId);

        $this->actingAs($officer)
            ->getJson("/requisition/{$order->id}/items/{$item->id}/attachments")
            ->assertOk();
    }

    // Not tested here: a shore role hitting this same route. The whole
    // /requisition/{order}/items/... group (this one included) sits behind
    // MemberMiddleWare, which only lets chief-officer/second-engineer
    // through at all - every other role, ship or shore, is redirected
    // before AttachmentController::forItem() ever runs. The vessel check
    // added there only ever matters for those two roles in practice, which
    // the two tests above already cover.

    // ---------------------------------------------------------------- storeStep2 re-link

    /**
     * storeStep2() deletes every OrderItem on the draft and recreates them
     * fresh (see RequisitionController::storeStep2()), which would
     * cascade-delete attachment_order_item for every line on ANY save, not
     * just a changed one - attachment_ids[itemId][] is what lets it re-link
     * exactly what was already there.
     */
    public function test_store_step2_keeps_attachments_when_the_line_set_is_rewritten(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $order = $this->makeOrder(['vessel_id' => $this->vesselId, 'status' => 'draft', 'category_id' => $category->id]);
        $line = $this->makeOrderItem($order->id, $item->id, 5);

        $officer = $this->makeUser('chief-officer', $this->vesselId);
        $file = $this->makeAttachment($officer->id);
        $line->attachments()->sync([$file->id]);

        $this->actingAs($officer)
            ->post("/requisition/{$order->id}/items", [
                'Category_Name' => $category->id,
                'item_id' => [$item->id],
                'item_qty' => [5],
                'attachment_ids' => [$item->id => [$file->id]],
            ])
            ->assertRedirect();

        $newLine = \App\OrderItem::where('order_id', $order->id)->where('item_id', $item->id)->first();

        $this->assertNotNull($newLine);
        $this->assertTrue($newLine->attachments->pluck('id')->contains($file->id), 'the attachment must survive the line being rewritten');
    }
}
