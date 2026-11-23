<?php

namespace Tests\Feature\Sync\Files;

use App\Sync\Files\FileIndex;
use App\Sync\SyncNodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Attachments: the records travel with the business records, the files
 * through their own channel afterwards, checked against their SHA-256.
 */
class AttachmentSyncTest extends FileSyncTestCase
{
    public function test_a_file_attached_on_board_reaches_the_office_intact(): void
    {
        [$orderId, $attachmentId, $bytes] = $this->crewAttachesAndSubmits();
        $uuid = $this->onShip(fn () => $this->uuidFor('attachments', $attachmentId));
        $onBoard = $this->attachmentOn('ship', $uuid);
        $this->assertSame(hash('sha256', $bytes), $onBoard->sha256, 'checksum recorded at upload');

        $result = $this->assertCycleOk();

        $this->assertSame(1, $result['summary']['files']['uploaded']);
        $inOffice = $this->attachmentOn('cloud', $uuid);
        $this->assertNotNull($inOffice);
        $this->assertSame([$onBoard->path, $onBoard->sha256, 'Maker quotation'], [$inOffice->path, $inOffice->sha256, $inOffice->title]);
        $this->assertSame($bytes, file_get_contents($this->fileOn('cloud', $inOffice->path)));
        $this->onCloud(function () use ($uuid, $orderId) {
            $this->assertSame(FileIndex::PRESENT, app(FileIndex::class)->entry('attachments', $uuid, 'path')->state);
            $this->assertSame(1, DB::table('attachment_order_item')->count(), 'the link to the line came too');
        });

        // Nothing more to send: the next cycle does not ask for files again.
        $this->requests = [];
        $this->assertCycleOk();
        $this->assertNotContains('/api/sync/v1/files/wanted', array_column($this->requests, 'path'));
    }

    public function test_an_upload_cut_off_carries_on_where_it_stopped(): void
    {
        [, $attachmentId, $bytes] = $this->crewAttachesAndSubmits(6);
        $uuid = $this->onShip(fn () => $this->uuidFor('attachments', $attachmentId));
        // The link drops on the fourth piece of the file.
        $this->network = fn ($path, $n) => str_contains($path, '/files/attachments/') && $n === 4 ? 'down' : 'up';

        $first = $this->syncCycle();
        $this->assertTrue($first['ok'], 'the records got through; only the file is unfinished');
        $this->assertArrayHasKey('error', $first['summary']['files']);
        $this->assertFileDoesNotExist($this->fileOn('cloud', $this->attachmentOn('ship', $uuid)->path));

        $this->network = 'up';
        $this->requests = [];
        $second = $this->assertCycleOk();

        $this->assertSame(1, $second['summary']['files']['uploaded']);
        $puts = array_filter($this->requests, fn ($r) => $r['method'] === 'PUT');
        $this->assertLessThan(7, count($puts), 'only the rest was sent, not the whole file again');
        $this->assertSame($bytes, file_get_contents($this->fileOn('cloud', $this->attachmentOn('cloud', $uuid)->path)));
    }

    public function test_the_offices_file_on_a_procurement_stage_reaches_the_vessel_and_no_other(): void
    {
        [$orderId] = $this->crewAttachesAndSubmits();
        $this->assertCycleOk();
        $orderUuid = $this->onShip(fn () => $this->uuidFor('orders', $orderId));

        $bytes = $this->pdf();
        [$stepAttachment, $library] = $this->onCloud(function () use ($orderUuid, $bytes) {
            $this->as('gm.srd@office.test')->post('/attachments/upload', ['title' => 'Purchase order', 'file' => $this->upload('po.pdf', $bytes)])->assertOk();
            $this->as('gm.srd@office.test')->post('/attachments/upload', ['title' => 'Office only', 'file' => $this->upload('note.pdf', $this->pdf())])->assertOk();
            [$po, $note] = DB::table('attachments')->where('uploaded_by', $this->userId('gm.srd@office.test'))->orderBy('id')->pluck('id')->all();
            $stepId = DB::table('order_procurement_steps')->insertGetId([
                'order_id' => $this->idOf('orders', $orderUuid), 'step' => 'purchase_order', 'outcome' => 'done',
                'completed_by' => $this->userId('gm.srd@office.test'), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('attachment_order_step')->insert(['order_procurement_step_id' => $stepId, 'attachment_id' => $po, 'created_at' => now(), 'updated_at' => now()]);

            return [$this->uuidFor('attachments', $po), $this->uuidFor('attachments', $note)];
        });

        $result = $this->assertCycleOk();

        $this->assertSame(1, $result['summary']['files']['downloaded']);
        $onBoard = $this->attachmentOn('ship', $stepAttachment);
        $this->assertNotNull($onBoard, 'the stage\'s file record came with the stage');
        $this->assertSame($bytes, file_get_contents($this->fileOn('ship', $onBoard->path)));
        $this->assertSame(1, $this->onShip(fn () => DB::table('attachment_order_step')->count()));
        $this->assertNull($this->attachmentOn('ship', $library), 'an office file linked to nothing of this vessel stays in the office');

        // Another vessel, with its own valid credentials, gets nothing.
        $other = $this->onCloud(fn () => app(SyncNodes::class)->register('vessel', 8, 'MV OTHER master PC'));
        $this->onCloud(function () use ($other, $stepAttachment, $library) {
            foreach ([$stepAttachment, $library] as $uuid) {
                $response = $this->withHeaders(['X-Sync-Node' => $other['node']->node_id, 'Authorization' => 'Bearer '.$other['token'], 'X-Sync-Protocol' => '1'])
                    ->get("/api/sync/v1/files/attachments/{$uuid}/path?offset=0&length=1024");
                $response->assertNotFound();
            }
        });
    }

    public function test_a_file_that_does_not_match_its_checksum_is_never_put_in_place(): void
    {
        [$orderId] = $this->crewAttachesAndSubmits();
        $this->assertCycleOk();
        $orderUuid = $this->onShip(fn () => $this->uuidFor('orders', $orderId));
        $uuid = $this->onCloud(function () use ($orderUuid) {
            $this->as('gm.srd@office.test')->post('/attachments/upload', ['title' => 'Invoice', 'file' => $this->upload('inv.pdf', $this->pdf())])->assertOk();
            $id = (int) DB::table('attachments')->where('uploaded_by', $this->userId('gm.srd@office.test'))->value('id');
            $stepId = DB::table('order_procurement_steps')->insertGetId([
                'order_id' => $this->idOf('orders', $orderUuid), 'step' => 'invoice', 'outcome' => 'done',
                'completed_by' => $this->userId('gm.srd@office.test'), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('attachment_order_step')->insert(['order_procurement_step_id' => $stepId, 'attachment_id' => $id, 'created_at' => now(), 'updated_at' => now()]);

            return $this->uuidFor('attachments', $id);
        });
        // The office's copy is damaged after the record was made.
        $path = $this->attachmentOn('cloud', $uuid)->path;
        file_put_contents($this->fileOn('cloud', $path), 'tampered');
        touch($this->fileOn('cloud', $path), time() + 5);

        $result = $this->assertCycleOk();

        $this->assertSame(1, $result['summary']['files']['failed']);
        $this->assertFileDoesNotExist($this->fileOn('ship', $path));
        $entry = $this->onShip(fn () => app(FileIndex::class)->entry('attachments', $uuid, 'path'));
        $this->assertSame(FileIndex::WANTED, $entry->state, 'tried again later');
        $this->assertStringContainsString('checksum', $entry->last_error);
        $this->assertSame(1, (int) $entry->attempts);
    }

    public function test_a_vessel_cannot_make_the_office_store_or_serve_a_file_outside_its_storage(): void
    {
        [, $attachmentId] = $this->crewAttachesAndSubmits();
        $this->assertCycleOk();
        $uuid = $this->onShip(fn () => $this->uuidFor('attachments', $attachmentId));

        foreach (['attachments/../../.env', 'attachments/12/x.php', '/etc/passwd', 'images/userphoto/x.jpg'] as $evil) {
            $this->onShip(fn () => DB::table('attachments')->where('id', $attachmentId)->update(['path' => $evil, 'updated_at' => now()->addMinute()]));
            $result = $this->syncCycle();

            $this->assertSame(1, $result['summary']['pushed']['rejected'], $evil);
            $this->assertStringStartsWith('attachments/', $this->attachmentOn('cloud', $uuid)->path, 'the office copy is unchanged');
        }
        $this->assertStringContainsString('refused', (string) $this->onCloud(fn () => DB::table('sync_inbox')->where('status', 'failed')->value('last_error')));
        $this->assertFileDoesNotExist($this->root.'/cloud/.env');
    }

    public function test_a_damaged_file_on_board_is_found_and_fetched_again(): void
    {
        [, $attachmentId, $bytes] = $this->crewAttachesAndSubmits();
        $this->assertCycleOk();
        $path = $this->onShip(fn () => DB::table('attachments')->where('id', $attachmentId)->value('path'));
        file_put_contents($this->fileOn('ship', $path), 'disk error');

        $this->onShip(fn () => $this->artisan('sync:files', ['action' => 'verify'])->expectsOutputToContain('does not match its checksum')->assertFailed()->run());
        $this->onShip(fn () => $this->artisan('sync:health')->expectsOutputToContain('damaged or missing')->assertExitCode(2)->run());

        $this->onShip(fn () => $this->artisan('sync:files', ['action' => 'verify', '--refetch' => true])->assertFailed()->run());
        $result = $this->assertCycleOk();

        $this->assertSame(1, $result['summary']['files']['downloaded'], 'the vessel\'s own file, back from the office');
        $this->assertSame($bytes, file_get_contents($this->fileOn('ship', $path)));
        $this->onShip(fn () => $this->artisan('sync:files', ['action' => 'verify'])->assertSuccessful()->run());
    }

    public function test_an_office_signature_comes_down_with_its_file(): void
    {
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $this->onCloud(function () use ($gif) {
            $this->as('tech.supt@office.test')->post('/change/file', ['signature' => $this->upload('sign.svg', $gif)])->assertRedirect();
        });
        $sign = $this->onCloud(fn () => DB::table('users')->where('email', 'tech.supt@office.test')->value('sign'));
        $this->assertMatchesRegularExpression('#^images/signature/[a-z0-9]{32}\.gif$#', $sign, 'random name, extension from the content');

        $this->assertCycleOk();

        $this->assertSame($sign, $this->onShip(fn () => DB::table('users')->where('email', 'tech.supt@office.test')->value('sign')), 'a superintendent\'s signature, shown on board');
        $this->assertSame($gif, file_get_contents($this->fileOn('ship', $sign, 'public')));
    }

    public function test_files_never_hold_up_the_records(): void
    {
        $this->crewAttachesAndSubmits();
        $this->network = fn ($path) => str_contains($path, '/files/') ? 'down' : 'up';

        $result = $this->syncCycle();

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $this->onCloud(fn () => DB::table('orders')->count()), 'the requisition is in the office');
        $this->assertStringContainsString('not reachable', $result['summary']['files']['error']);
        $this->assertSame(1, $this->onCloud(fn () => DB::table('sync_files')->where('state', FileIndex::WANTED)->count()));
        Http::assertSentCount(count($this->requests));
    }
}
