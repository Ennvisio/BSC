<?php

namespace Tests\Feature\Sync\Files;

use App\Attachment;
use App\Sync\Exceptions\WrongSideEdit;
use App\Sync\Models\SyncConflict;
use App\Sync\WrongSideEdits;
use Illuminate\Support\Facades\DB;

/**
 * PD-59 B (approved 2026-11-24): an attachment belongs to the side that
 * uploaded it. Only that side changes it: the other side is refused in the
 * application, and a change that still arrives by sync is refused and
 * flagged, never applied. No deletion is added.
 */
class AttachmentOwnershipSyncTest extends FileSyncTestCase
{
    public function test_each_file_records_the_side_that_uploaded_it(): void
    {
        [, $attachmentId] = $this->crewAttachesAndSubmits();
        $uuid = $this->onShip(fn () => $this->uuidFor('attachments', $attachmentId));
        $this->assertSame('vessel', $this->attachmentOn('ship', $uuid)->owner_side, 'uploaded on board');

        $this->assertCycleOk();
        $this->assertSame('vessel', $this->attachmentOn('cloud', $uuid)->owner_side, 'travels unchanged');

        $this->onCloud(function () {
            $this->as('gm.srd@office.test')->post('/attachments/upload', ['title' => 'Purchase order', 'file' => $this->upload('po.pdf', $this->pdf())])->assertOk();
            $this->assertSame('cloud', DB::table('attachments')->where('uploaded_by', $this->userId('gm.srd@office.test'))->value('owner_side'), 'uploaded in the office');

            // A vessel without its own server works on the cloud: its crew's file is the vessel's.
            $file = new Attachment;
            $file->forceFill(['uploaded_by' => $this->userId('master@mvother.test'), 'title' => 'Survey report', 'original_filename' => 'r.pdf',
                'path' => 'attachments/x/r.pdf', 'mime_type' => 'application/pdf', 'kind' => 'pdf', 'file_size' => 1])->save();
            $this->assertSame('vessel', $file->fresh()->owner_side);
        });
    }

    public function test_the_office_cannot_change_a_vessel_file_and_a_change_that_slips_through_is_refused_on_board(): void
    {
        [, $attachmentId] = $this->crewAttachesAndSubmits();
        $uuid = $this->onShip(fn () => $this->uuidFor('attachments', $attachmentId));
        $this->assertCycleOk();

        // In the application on the cloud: refused before anything is saved.
        $this->onCloud(function () use ($uuid) {
            $file = Attachment::where('sync_uuid', $uuid)->sole();
            try {
                $file->forceFill(['title' => 'Changed in the office'])->save();
                $this->fail('the office changed the vessel\'s file');
            } catch (WrongSideEdit $e) {
                $this->assertStringContainsString('vessel', $e->getMessage());
            }
            $this->assertThrows(fn () => DB::table('attachments')->where('sync_uuid', $uuid)->update(['hidden_at' => now()]), WrongSideEdit::class);
        });

        // A change made some other way is not taken on board, and is flagged.
        $this->onCloud(fn () => WrongSideEdits::outsideTheApplication(fn () => DB::table('attachments')->where('sync_uuid', $uuid)
            ->update(['title' => 'Changed in the office', 'sync_version' => DB::raw('sync_version + 1'), 'updated_at' => now()])));
        $result = $this->syncCycle();
        $this->assertSame(1, $result['summary']['pulled']['rejected']);

        $this->assertSame('Maker quotation', $this->attachmentOn('ship', $uuid)->title, 'the vessel\'s file is unchanged');
        $flag = $this->onShip(fn () => SyncConflict::query()->sole());
        $this->assertSame(['refused', 'needs_review', 'attachments'], [$flag->kind, $flag->status, $flag->table_name]);
        $this->assertStringContainsString('kept by the vessel', $flag->note);

        // Retried: still one flag, still unchanged.
        $this->syncCycle();
        $this->assertSame(1, $this->onShip(fn () => SyncConflict::query()->count()));
        $this->assertSame('Maker quotation', $this->attachmentOn('ship', $uuid)->title);
    }

    public function test_the_vessel_cannot_change_an_office_file_and_a_change_that_slips_through_is_refused_in_the_office(): void
    {
        $uuid = $this->officeFileOnAStage();
        $this->assertSame('cloud', $this->attachmentOn('ship', $uuid)->owner_side);

        $this->onShip(function () use ($uuid) {
            $this->assertThrows(fn () => Attachment::where('sync_uuid', $uuid)->sole()->forceFill(['title' => 'Changed on board'])->save(), WrongSideEdit::class);
            $this->assertThrows(fn () => DB::table('attachments')->where('sync_uuid', $uuid)->update(['title' => 'Changed on board']), WrongSideEdit::class);
        });

        $this->onShip(fn () => WrongSideEdits::outsideTheApplication(fn () => DB::table('attachments')->where('sync_uuid', $uuid)
            ->update(['title' => 'Changed on board', 'sync_version' => DB::raw('sync_version + 1'), 'updated_at' => now()])));
        $result = $this->syncCycle();
        $this->assertSame(1, $result['summary']['pushed']['rejected']);

        $this->assertSame('Purchase order', $this->attachmentOn('cloud', $uuid)->title, 'the office\'s file is unchanged');
        $flag = $this->onCloud(fn () => SyncConflict::query()->sole());
        $this->assertSame(['refused', 'needs_review', 'attachments'], [$flag->kind, $flag->status, $flag->table_name]);
        $this->assertStringContainsString('kept by the cloud', $flag->note);
    }

    public function test_each_side_still_changes_its_own_files(): void
    {
        [, $attachmentId] = $this->crewAttachesAndSubmits();
        $uuid = $this->onShip(fn () => $this->uuidFor('attachments', $attachmentId));
        $this->assertCycleOk();

        $this->onShip(fn () => Attachment::where('sync_uuid', $uuid)->sole()->forceFill(['title' => 'Maker quotation rev 2'])->save());
        $this->assertCycleOk();
        $this->assertSame('Maker quotation rev 2', $this->attachmentOn('cloud', $uuid)->title);

        // The office changes a file of its own, and it reaches the vessel.
        $office = $this->officeFileOnAStage();
        $this->onCloud(fn () => Attachment::where('sync_uuid', $office)->sole()->forceFill(['title' => 'Purchase order rev 2'])->save());
        $this->assertCycleOk();
        $this->assertSame('Purchase order rev 2', $this->attachmentOn('ship', $office)->title);
        $this->assertSame(0, $this->onCloud(fn () => SyncConflict::query()->count()) + $this->onShip(fn () => SyncConflict::query()->count()));
    }

    public function test_a_file_is_never_duplicated_by_repeated_cycles(): void
    {
        [, $attachmentId] = $this->crewAttachesAndSubmits();
        $uuid = $this->onShip(fn () => $this->uuidFor('attachments', $attachmentId));
        $this->officeFileOnAStage();
        $this->assertCycleOk();
        $this->assertCycleOk();

        $this->assertSame(1, $this->onCloud(fn () => DB::table('attachments')->where('sync_uuid', $uuid)->count()));
        $this->assertSame(
            $this->onCloud(fn () => DB::table('attachments')->whereIn('owner_side', ['vessel', 'cloud'])->where('title', '!=', 'Office only')->orderBy('sync_uuid')->pluck('owner_side', 'sync_uuid')->all()),
            $this->onShip(fn () => DB::table('attachments')->orderBy('sync_uuid')->pluck('owner_side', 'sync_uuid')->all())
        );
    }

    public function test_existing_files_are_given_their_owner_from_the_uploader(): void
    {
        [, $attachmentId] = $this->crewAttachesAndSubmits();
        $this->assertCycleOk();
        $this->onCloud(function () {
            $this->as('gm.srd@office.test')->post('/attachments/upload', ['title' => 'Purchase order', 'file' => $this->upload('po.pdf', $this->pdf())])->assertOk();
            \App\Sync\SyncContext::withoutCapture(fn () => WrongSideEdits::outsideTheApplication(fn () => DB::table('attachments')->update(['owner_side' => null])));

            (require database_path('migrations/2026_11_27_100000_add_owner_side_to_attachments_table.php'))->up();

            $this->assertSame(['Maker quotation' => 'vessel', 'Purchase order' => 'cloud'], DB::table('attachments')->orderBy('title')->pluck('owner_side', 'title')->all());
        });
    }

    /** The office links a file of its own to a procurement stage of a vessel requisition; it reaches the vessel. Returns its UUID. */
    private function officeFileOnAStage(): string
    {
        [$orderId] = $this->crewAttachesAndSubmits();
        $this->assertCycleOk();
        $orderUuid = $this->onShip(fn () => $this->uuidFor('orders', $orderId));
        $uuid = $this->onCloud(function () use ($orderUuid) {
            $this->as('gm.srd@office.test')->post('/attachments/upload', ['title' => 'Purchase order', 'file' => $this->upload('po.pdf', $this->pdf())])->assertOk();
            $id = (int) DB::table('attachments')->where('uploaded_by', $this->userId('gm.srd@office.test'))->value('id');
            $stepId = DB::table('order_procurement_steps')->insertGetId([
                'order_id' => $this->idOf('orders', $orderUuid), 'step' => 'purchase_order', 'outcome' => 'done',
                'completed_by' => $this->userId('gm.srd@office.test'), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('attachment_order_step')->insert(['order_procurement_step_id' => $stepId, 'attachment_id' => $id, 'created_at' => now(), 'updated_at' => now()]);

            return $this->uuidFor('attachments', $id);
        });
        $this->assertCycleOk();

        return $uuid;
    }
}
