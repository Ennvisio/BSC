<?php

namespace Tests\Feature\Sync\Files;

use App\Sync\Files\FileIndex;
use Illuminate\Support\Facades\DB;

/**
 * PD-22 (approved 2026-11-24): the servers exchange files up to the size
 * the vessel server accepts (20 MB). A larger file can never travel, so it
 * is marked failed at once, for an operator, instead of being tried again.
 */
class FileSizeLimitTest extends FileSyncTestCase
{
    public function test_the_limit_is_what_the_vessel_server_accepts_and_covers_every_upload_the_application_allows(): void
    {
        $ini = (string) file_get_contents(base_path('deploy/windows/templates/php.ini'));
        $this->assertMatchesRegularExpression('/^upload_max_filesize\s*=\s*20M\s*$/m', $ini);
        $this->assertSame(20 * 1024 * 1024, (int) config('sync.files.max_file_bytes'));

        // Attachments 15 MB, certificate copies 10 MB, photos and signatures 5 MB (unchanged).
        foreach ([15 * 1024, 10000, 5120] as $kb) {
            $this->assertLessThanOrEqual(config('sync.files.max_file_bytes'), $kb * 1024);
        }
    }

    public function test_a_vessel_file_over_the_limit_is_marked_failed_in_the_office_and_not_asked_for_again(): void
    {
        config(['sync.files.max_file_bytes' => 4 * 1024]);
        [, $attachmentId] = $this->crewAttachesAndSubmits(6);
        $uuid = $this->onShip(fn () => $this->uuidFor('attachments', $attachmentId));

        $first = $this->syncCycle();
        $this->assertTrue($first['ok'], 'the records travel; only the file does not');

        $entry = $this->onCloud(fn () => app(FileIndex::class)->entry('attachments', $uuid, 'path'));
        $this->assertSame([FileIndex::FAILED, 1], [$entry->state, (int) $entry->attempts]);
        $this->assertStringContainsString('PD-22', $entry->last_error);

        $this->requests = [];
        $this->assertCycleOk();
        $this->assertSame([], array_filter($this->requests, fn ($r) => str_contains($r['path'], '/files/attachments/')), 'not tried again');
    }

    public function test_an_office_file_over_the_limit_is_marked_failed_on_board_after_one_attempt(): void
    {
        [$orderId] = $this->crewAttachesAndSubmits();
        $this->assertCycleOk();
        $orderUuid = $this->onShip(fn () => $this->uuidFor('orders', $orderId));
        $uuid = $this->onCloud(function () use ($orderUuid) {
            $this->as('gm.srd@office.test')->post('/attachments/upload', ['title' => 'Drawings', 'file' => $this->upload('d.pdf', $this->pdf(6))])->assertOk();
            $id = (int) DB::table('attachments')->where('uploaded_by', $this->userId('gm.srd@office.test'))->value('id');
            $stepId = DB::table('order_procurement_steps')->insertGetId([
                'order_id' => $this->idOf('orders', $orderUuid), 'step' => 'purchase_order', 'outcome' => 'done',
                'completed_by' => $this->userId('gm.srd@office.test'), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('attachment_order_step')->insert(['order_procurement_step_id' => $stepId, 'attachment_id' => $id, 'created_at' => now(), 'updated_at' => now()]);

            return $this->uuidFor('attachments', $id);
        });
        config(['sync.files.max_file_bytes' => 4 * 1024]);

        $this->syncCycle();

        $entry = $this->onShip(fn () => app(FileIndex::class)->entry('attachments', $uuid, 'path'));
        $this->assertSame([FileIndex::FAILED, 1], [$entry->state, (int) $entry->attempts]);
        $this->assertStringContainsString('PD-22', $entry->last_error);
        $this->assertSame([], $this->onShip(fn () => app(FileIndex::class)->due($this->cloudNode)), 'not due again');
    }
}
