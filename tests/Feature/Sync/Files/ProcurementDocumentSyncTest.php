<?php

namespace Tests\Feature\Sync\Files;

use App\Attachment;
use App\Order;
use App\Support\UserStillReferenced;
use App\Sync\Exceptions\WrongSideEdit;
use App\Sync\Models\SyncConflict;
use App\Sync\SyncContext;
use App\Sync\WrongSideEdits;
use App\User;
use Illuminate\Support\Facades\DB;

/**
 * PD-22 (approved 2026-11-24): procurement documents are the office's. They
 * reach the vessel as read-only copies, which crew may view and download
 * but not edit, replace, overwrite or remove. Only the office maintains
 * them. Receipt & Verification documents stay the vessel's.
 *
 * Also the owner's PD-21 / PD-74 conclusion: a requisition shows each
 * signer's current signature, so users named on requisitions, and their
 * signature files, are never deleted.
 */
class ProcurementDocumentSyncTest extends FileSyncTestCase
{
    public function test_an_office_document_reaches_the_vessel_as_a_read_only_copy_crew_can_view_and_download(): void
    {
        [$uuid, $bytes] = $this->officeDocumentOnAnOrderStage();

        $this->onShip(function () use ($uuid, $bytes) {
            $doc = Attachment::where('sync_uuid', $uuid)->sole();
            $this->assertSame(Attachment::OFFICE, $doc->owner_side);

            $this->as('co@mvtest.test')->get("/attachments/{$doc->id}/view")->assertOk();
            $download = $this->as('master@mvtest.test')->get("/attachments/{$doc->id}/view?download=1")->assertOk();
            $this->assertStringContainsString('attachment;', (string) $download->headers->get('Content-Disposition'));
            $this->assertSame($bytes, $download->streamedContent());

            $html = view('partials.procurement-doc-link', ['doc' => $doc])->render();
            $this->assertStringContainsString('Office document, view only', $html);
            $this->assertStringContainsString('download=1', $html);
        });

        // In the office the same document is the office's own: no label.
        $this->onCloud(fn () => $this->assertStringNotContainsString('view only',
            view('partials.procurement-doc-link', ['doc' => Attachment::where('sync_uuid', $uuid)->sole()])->render()));
    }

    public function test_crew_cannot_edit_replace_unlink_or_hide_an_office_document_on_board(): void
    {
        [$uuid] = $this->officeDocumentOnAnOrderStage();

        $this->onShip(function () use ($uuid) {
            $doc = Attachment::where('sync_uuid', $uuid)->sole();
            $step = (int) DB::table('attachment_order_step')->where('attachment_id', $doc->id)->value('order_procurement_step_id');
            $this->as('co@mvtest.test');

            $this->assertThrows(fn () => $doc->forceFill(['title' => 'Edited on board'])->save(), WrongSideEdit::class);
            $this->assertThrows(fn () => DB::table('attachments')->where('id', $doc->id)->update(['path' => 'attachments/9/other.pdf']), WrongSideEdit::class);
            $this->assertThrows(fn () => DB::table('attachments')->where('id', $doc->id)->delete(), WrongSideEdit::class);
            $this->assertThrows(fn () => DB::table('attachment_order_step')->where('attachment_id', $doc->id)->delete(), WrongSideEdit::class);
            $this->assertThrows(fn () => DB::table('attachment_order_step')->where('attachment_id', $doc->id)->update(['attachment_id' => $doc->id]), WrongSideEdit::class);

            // Replacing it by putting a crew file on the office's stage.
            $own = $this->crewLibraryFile();
            $this->assertThrows(fn () => DB::table('attachment_order_step')->insert(['order_procurement_step_id' => $step, 'attachment_id' => $own, 'created_at' => now(), 'updated_at' => now()]), WrongSideEdit::class);

            // The library's "delete" is the uploader's only, and vessel users do not delete (ADR-006).
            $this->as('co@mvtest.test')->postJson("/attachments/{$doc->id}/delete")->assertForbidden();

            $this->assertSame('Purchase order', $doc->fresh()->title);
            $this->assertNull($doc->fresh()->hidden_at);
            $this->assertSame(1, DB::table('attachment_order_step')->count());
        });
    }

    public function test_a_vessel_change_to_an_office_stage_that_slips_through_is_refused_in_the_office_and_flagged(): void
    {
        [$uuid] = $this->officeDocumentOnAnOrderStage();
        $this->onShip(function () use ($uuid) {
            $own = $this->crewLibraryFile();
            $step = (int) DB::table('attachment_order_step')->value('order_procurement_step_id');
            WrongSideEdits::outsideTheApplication(fn () => DB::table('attachment_order_step')
                ->insert(['order_procurement_step_id' => $step, 'attachment_id' => $own, 'created_at' => now(), 'updated_at' => now()]));
        });

        $result = $this->syncCycle();

        $this->assertGreaterThanOrEqual(1, $result['summary']['pushed']['rejected']);
        $this->onCloud(function () use ($uuid) {
            $this->assertSame(1, DB::table('attachment_order_step')->count(), 'the office stage keeps only its own document');
            $this->assertSame($uuid, DB::table('attachments')->where('id', DB::table('attachment_order_step')->value('attachment_id'))->value('sync_uuid'));
            $flag = SyncConflict::query()->where('table_name', 'attachment_order_step')->sole();
            $this->assertSame(['refused', 'needs_review'], [$flag->kind, $flag->status]);
            $this->assertStringContainsString('kept by the cloud', $flag->note);
        });
    }

    public function test_the_office_still_maintains_its_documents_and_cannot_touch_the_vessels_receipt_documents(): void
    {
        [$uuid] = $this->officeDocumentOnAnOrderStage();

        // The office adds a second document to its stage; it reaches the vessel.
        $second = $this->onCloud(function () {
            $this->as('gm.srd@office.test')->post('/attachments/upload', ['title' => 'Revised PO', 'file' => $this->upload('po2.pdf', $this->pdf())])->assertOk();
            $id = (int) DB::table('attachments')->where('title', 'Revised PO')->value('id');
            DB::table('attachment_order_step')->insert(['order_procurement_step_id' => (int) DB::table('attachment_order_step')->value('order_procurement_step_id'),
                'attachment_id' => $id, 'created_at' => now(), 'updated_at' => now()]);

            return $this->uuidFor('attachments', $id);
        });
        $this->assertCycleOk();
        $this->assertNotNull($this->attachmentOn('ship', $second));
        $this->assertSame(2, $this->onShip(fn () => DB::table('attachment_order_step')->count()));

        // Receipt & Verification is the vessel's stage: its document link is the vessel's.
        $receipt = $this->onShip(function () {
            $own = $this->crewLibraryFile();
            $orderId = (int) DB::table('orders')->value('id');
            $step = DB::table('order_procurement_steps')->insertGetId(['order_id' => $orderId, 'step' => 'receipt_verification', 'outcome' => 'done',
                'completed_by' => $this->userId('co@mvtest.test'), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('attachment_order_step')->insert(['order_procurement_step_id' => $step, 'attachment_id' => $own, 'created_at' => now(), 'updated_at' => now()]);

            return $this->uuidFor('attachments', $own);
        });
        $this->assertCycleOk();
        $this->onCloud(function () use ($receipt) {
            $id = (int) DB::table('attachments')->where('sync_uuid', $receipt)->value('id');
            $this->assertThrows(fn () => DB::table('attachment_order_step')->where('attachment_id', $id)->delete());
            $this->assertThrows(fn () => DB::table('attachment_order_step')->where('attachment_id', $id)->update(['updated_at' => now()]), WrongSideEdit::class);
        });
    }

    public function test_a_service_requisitions_office_documents_are_read_only_on_board_too(): void
    {
        $id = $this->onShip(fn () => DB::table('service_requisitions')->insertGetId([
            'vessel_id' => 7, 'req_no' => 'DK/SHR/03/2026', 'req_date' => now()->toDateString(), 'service_type' => 'shore_repair',
            'description' => 'Overhaul', 'status' => 'approved by chief-officer', 'is_submitted' => true, 'created_by' => $this->userId('co@mvtest.test'),
            'created_by_role' => 'chief-officer', 'created_at' => now(), 'updated_at' => now(),
        ]));
        $srUuid = $this->onShip(fn () => $this->uuidFor('service_requisitions', $id));
        $this->assertCycleOk();

        $doc = $this->onCloud(function () use ($srUuid) {
            $this->as('gm.srd@office.test')->post('/attachments/upload', ['title' => 'Repair quotation', 'file' => $this->upload('q.pdf', $this->pdf())])->assertOk();
            $file = (int) DB::table('attachments')->where('title', 'Repair quotation')->value('id');
            $step = DB::table('service_requisition_procurement_steps')->insertGetId([
                'service_requisition_id' => $this->idOf('service_requisitions', $srUuid), 'step' => 'purchase_order', 'outcome' => 'done',
                'completed_by' => $this->userId('gm.srd@office.test'), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('attachment_service_req_step')->insert(['step_id' => $step, 'attachment_id' => $file, 'created_at' => now(), 'updated_at' => now()]);

            return $this->uuidFor('attachments', $file);
        });
        $this->assertCycleOk();

        $this->onShip(function () use ($doc) {
            $file = Attachment::where('sync_uuid', $doc)->sole();
            $this->assertFileExists($this->fileOn('ship', $file->path));
            $this->assertThrows(fn () => DB::table('attachment_service_req_step')->where('attachment_id', $file->id)->delete(), WrongSideEdit::class);
            $this->assertThrows(fn () => $file->forceFill(['title' => 'Changed'])->save(), WrongSideEdit::class);
        });
    }

    public function test_a_requisition_shows_each_signers_current_signature_and_signers_are_never_deleted(): void
    {
        $orderId = $this->draftOnBoard(['ME-100' => 2]);
        $this->submitOnBoard($orderId);
        $this->onShip(fn () => $this->approve('master@mvtest.test', $orderId)->assertOk());
        $this->assertCycleOk();

        $this->onShip(function () use ($orderId) {
            $master = $this->userId('master@mvtest.test');
            foreach (['images/signature/first.png', 'images/signature/second.png'] as $sign) {
                SyncContext::withoutCapture(fn () => WrongSideEdits::outsideTheApplication(fn () => DB::table('users')->where('id', $master)->update(['sign' => $sign])));
                $row = collect(Order::findOrFail($orderId)->signatories())->firstWhere('role', 'Master');
                $this->assertSame($sign, $row['user']->sign, 'the same person: their current signature');
            }
        });

        // Another person takes over as master with an account of their own
        // (users are created in the office): the old requisition still names,
        // and shows, the previous master.
        $uuid = $this->onShip(fn () => $this->uuidFor('orders', $orderId));
        $this->onCloud(function () use ($uuid) {
            $master = $this->userId('master@mvtest.test');
            SyncContext::withoutCapture(fn () => DB::table('users')->where('id', $master)->update(['sign' => 'images/signature/second.png']));
            $newMaster = DB::table('users')->insertGetId(['name' => 'New master', 'email' => 'new.master@mvtest.test', 'password' => 'x', 'sign' => 'images/signature/new.png', 'created_at' => now(), 'updated_at' => now()]);
            $row = collect(Order::findOrFail($this->idOf('orders', $uuid))->signatories())->firstWhere('role', 'Master');
            $this->assertSame([$master, 'images/signature/second.png'], [(int) $row['user']->id, $row['user']->sign]);
            $this->assertNotSame($newMaster, (int) $row['user']->id);
        });

        // Never deleted while a requisition names them: by model, by query, on either server.
        foreach (['cloud', 'ship'] as $server) {
            $this->on($server, function () {
                $master = $this->userId('master@mvtest.test');
                $this->assertThrows(fn () => WrongSideEdits::outsideTheApplication(fn () => User::findOrFail($master)->delete()), UserStillReferenced::class);
                $this->assertThrows(fn () => WrongSideEdits::outsideTheApplication(fn () => DB::table('users')->where('id', $master)->delete()), UserStillReferenced::class);
                $this->assertTrue(DB::table('users')->where('id', $master)->exists());
            });
        }

        // A user nobody's requisition names is not held back by this rule.
        $this->onCloud(function () {
            $spare = DB::table('users')->insertGetId(['name' => 'Spare', 'email' => 'spare@office.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('users')->where('id', $spare)->delete();
            $this->assertFalse(DB::table('users')->where('id', $spare)->exists());
        });
    }

    public function test_changing_a_signature_keeps_the_previous_signature_file(): void
    {
        $this->onCloud(function () {
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
            $this->as('gm.srd@office.test')->post('/change/file', ['signature' => $this->upload('a.png', $png)]);
            $first = (string) DB::table('users')->where('email', 'gm.srd@office.test')->value('sign');
            $this->as('gm.srd@office.test')->post('/change/file', ['signature' => $this->upload('b.png', $png.' ')]);
            $second = (string) DB::table('users')->where('email', 'gm.srd@office.test')->value('sign');

            $this->assertNotSame($first, $second);
            $this->assertFileExists($this->fileOn('cloud', $first, 'public'), 'the previous signature file stays');
            $this->assertFileExists($this->fileOn('cloud', $second, 'public'));
        });
    }

    /** The office puts a PO on an office stage of a vessel requisition; it reaches the vessel. Returns [attachment UUID, bytes]. */
    private function officeDocumentOnAnOrderStage(): array
    {
        [$orderId] = $this->crewAttachesAndSubmits();
        $this->assertCycleOk();
        $orderUuid = $this->onShip(fn () => $this->uuidFor('orders', $orderId));
        $bytes = $this->pdf();
        $uuid = $this->onCloud(function () use ($orderUuid, $bytes) {
            $this->as('gm.srd@office.test')->post('/attachments/upload', ['title' => 'Purchase order', 'file' => $this->upload('po.pdf', $bytes)])->assertOk();
            $id = (int) DB::table('attachments')->where('title', 'Purchase order')->value('id');
            $stepId = DB::table('order_procurement_steps')->insertGetId([
                'order_id' => $this->idOf('orders', $orderUuid), 'step' => 'purchase_order', 'outcome' => 'done',
                'completed_by' => $this->userId('gm.srd@office.test'), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('attachment_order_step')->insert(['order_procurement_step_id' => $stepId, 'attachment_id' => $id, 'created_at' => now(), 'updated_at' => now()]);

            return $this->uuidFor('attachments', $id);
        });
        $this->assertCycleOk();

        return [$uuid, $bytes];
    }

    /** A file in the chief officer's own library on board; returns its id. */
    private function crewLibraryFile(): int
    {
        $this->as('co@mvtest.test')->post('/attachments/upload', ['title' => 'Crew photo', 'file' => $this->upload('c.pdf', $this->pdf())])->assertOk();

        return (int) DB::table('attachments')->where('title', 'Crew photo')->value('id');
    }
}
