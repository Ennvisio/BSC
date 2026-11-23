<?php

namespace Tests\Feature\Sync\Files;

use App\Sync\Engine\InboxProcessor;
use App\Sync\Engine\VesselSide;
use App\Sync\Files\FileIndex;
use App\Sync\Models\SyncConflict;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The certificate copy history (PD-62) between servers (owner's
 * instruction, 2026-11-23): each copy is an immutable historical record,
 * with its own UUID, the uploader and time it was made with, and its
 * file's checksum, which the file channel checks wherever the file goes.
 * Receiving a copy again changes nothing; both sides uploading while apart
 * keeps every copy.
 */
class CertificateCopySyncTest extends FileSyncTestCase
{
    private int $certificateCategory;

    protected function buildServerSchema(): void
    {
        parent::buildServerSchema();
        // As the MySQL-only certificate migrations leave these columns.
        Schema::table('vessel_certificates', function (Blueprint $t) {
            $t->string('issue_auth')->nullable()->change();
            $t->unsignedInteger('certificate_id')->nullable()->change();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->certificateCategory = $this->onCloud(fn () => DB::table('certificate_categories')->insertGetId([
            'name' => 'Statutory', 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]));
        $this->assertCycleOk();
    }

    private function payload(string $server, array $extra): array
    {
        $category = $this->on($server, fn () => $this->idOf('certificate_categories', $this->onCloud(fn () => $this->uuidFor('certificate_categories', $this->certificateCategory))));

        return $extra + ['Vessel_Name' => 7, 'category_id' => $category, 'title' => 'IOPP Certificate', 'Issue_Date' => '2026-01-01',
            'is_permanent' => 1, 'Issuing_Authority' => 'Class'];
    }

    /** The master adds a certificate with a copy on board; returns [certificate uuid, copy uuid, bytes]. */
    private function certificateFromBoard(): array
    {
        $bytes = $this->pdf();
        $id = $this->onShip(function () use ($bytes) {
            $this->as('master@mvtest.test')->post('/certificate/store', $this->payload('ship', ['Certificate_Copy' => $this->upload('iopp.pdf', $bytes)]))->assertOk();

            return (int) DB::table('vessel_certificates')->max('id');
        });

        return [
            $this->onShip(fn () => $this->uuidFor('vessel_certificates', $id)),
            $this->onShip(fn () => (string) DB::table('vessel_certificate_copies')->where('vessel_certificate_id', $id)->value('sync_uuid')),
            $bytes,
        ];
    }

    private function copiesOn(string $server, string $certificateUuid)
    {
        return $this->on($server, fn () => DB::table('vessel_certificate_copies')
            ->where('vessel_certificate_id', $this->idOf('vessel_certificates', $certificateUuid))->orderBy('path')->get());
    }

    public function test_a_copy_made_on_board_reaches_the_office_with_its_author_time_and_checksum(): void
    {
        [$certificate, $copy, $bytes] = $this->certificateFromBoard();
        $onBoard = $this->row('ship', 'vessel_certificate_copies', $copy);
        $this->assertSame(hash('sha256', $bytes), $onBoard->sha256);

        $result = $this->assertCycleOk();

        $inOffice = $this->row('cloud', 'vessel_certificate_copies', $copy);
        $this->assertNotNull($inOffice, 'the same record, by its UUID');
        $this->assertSame(
            [$onBoard->path, $onBoard->sha256, (int) $onBoard->file_size, (string) $onBoard->uploaded_at],
            [$inOffice->path, $inOffice->sha256, (int) $inOffice->file_size, (string) $inOffice->uploaded_at],
            'the original details, not the time it arrived');
        $this->assertSame($this->onCloud(fn () => $this->userId('master@mvtest.test')), (int) $inOffice->uploaded_by, 'the original uploader');
        $this->assertSame($this->onCloud(fn () => $this->idOf('vessel_certificates', $certificate)), (int) $inOffice->vessel_certificate_id);

        $this->assertGreaterThanOrEqual(1, $result['summary']['files']['uploaded']);
        $this->assertSame($bytes, file_get_contents($this->fileOn('cloud', $inOffice->path, 'public')));
        $this->assertSame(FileIndex::PRESENT, $this->onCloud(fn () => app(FileIndex::class)->entry('vessel_certificate_copies', $copy, 'path')->state));
    }

    public function test_receiving_a_copy_again_changes_nothing(): void
    {
        [, $copy] = $this->certificateFromBoard();
        $this->assertCycleOk();
        $before = $this->row('cloud', 'vessel_certificate_copies', $copy);

        // The same change delivered twice, and again under a new event id
        // (a resend after a lost answer).
        $results = $this->onCloud(function () use ($copy) {
            $event = DB::table('sync_inbox')->where('table_name', 'vessel_certificate_copies')->where('row_uuid', $copy)->sole();
            $processor = app(InboxProcessor::class);
            $raw = ['event_id' => $event->event_id, 'table' => 'vessel_certificate_copies', 'row_uuid' => $event->row_uuid, 'op' => $event->operation,
                'version' => (int) $event->row_version, 'vessel_id' => 7, 'values' => json_decode($event->payload, true), 'actor' => $event->actor_uuid];
            $side = app(\App\Sync\Engine\CloudSide::class, ['node' => \App\Sync\Models\SyncNode::query()->where('node_id', $this->vesselNode)->sole()]);

            return [$processor->process($this->vesselNode, [$raw], $side), $processor->process($this->vesselNode, [['event_id' => (string) Str::uuid()] + $raw], $side)];
        });

        $this->assertSame(InboxProcessor::DUPLICATE, $results[0][0]['status']);
        $this->assertNull($results[1][0]['conflict']);
        $this->assertSame((array) $before, (array) $this->row('cloud', 'vessel_certificate_copies', $copy));
        $this->assertSame(1, $this->onCloud(fn () => DB::table('vessel_certificate_copies')->where('sync_uuid', $copy)->count()));
    }

    public function test_copies_made_on_both_sides_while_apart_are_all_kept_everywhere(): void
    {
        // A certificate whose copy dates from before the history began.
        $old = $this->pdf();
        $certificate = $this->onShip(function () use ($old) {
            @mkdir(dirname($this->fileOn('ship', 'images/cert_copy/old.pdf', 'public')), 0777, true);
            file_put_contents($this->fileOn('ship', 'images/cert_copy/old.pdf', 'public'), $old);
            $id = DB::table('vessel_certificates')->insertGetId([
                'vessel_id' => 7, 'title' => 'Registry', 'issue_date' => '2025-01-01', 'cert_copy' => 'images/cert_copy/old.pdf',
                'status' => true, 'created_by' => $this->userId('master@mvtest.test'), 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
            ]);

            return $this->uuidFor('vessel_certificates', $id);
        });
        $this->assertCycleOk();
        $this->assertSame($old, file_get_contents($this->fileOn('cloud', 'images/cert_copy/old.pdf', 'public')));

        // Apart: the master replaces it on board, SRD in the office.
        $this->onShip(fn () => $this->as('master@mvtest.test')->post('/certificate/update', $this->payload('ship', [
            'Cert_Id' => $this->idOf('vessel_certificates', $certificate), 'title' => 'Registry', 'Certificate_Copy' => $this->upload('a.pdf', $this->pdf()),
        ]))->assertOk());
        $this->travel(5)->seconds();
        $this->onCloud(fn () => $this->as('gm.srd@office.test')->post('/certificate/update', $this->payload('cloud', [
            'Cert_Id' => $this->idOf('vessel_certificates', $certificate), 'title' => 'Registry', 'Certificate_Copy' => $this->upload('b.pdf', $this->pdf()),
        ]))->assertOk());

        $this->assertCycleOk();
        $this->assertCycleOk();

        $ship = $this->copiesOn('ship', $certificate);
        $cloud = $this->copiesOn('cloud', $certificate);
        $this->assertCount(3, $ship, 'the old copy, the one made on board and the one made in the office');
        $this->assertSame($ship->pluck('path')->all(), $cloud->pluck('path')->all());
        $this->assertSame($ship->pluck('sha256')->all(), $cloud->pluck('sha256')->all());
        $this->assertSame(1, $ship->where('path', 'images/cert_copy/old.pdf')->count(), 'the old copy, listed on both sides, is one record');
        $this->assertSame(1, $cloud->where('path', 'images/cert_copy/old.pdf')->count());
        foreach (['ship' => $ship, 'cloud' => $cloud] as $server => $copies) {
            foreach ($copies as $copy) {
                $this->assertSame($copy->sha256, hash_file('sha256', $this->fileOn($server, $copy->path, 'public')), "every file is on the {$server} and intact");
            }
        }
        // The certificate's current copy was changed on both sides: the
        // usual certificate rule decides it, and a person is told.
        $this->assertTrue($this->onCloud(fn () => SyncConflict::query()->where('table_name', 'vessel_certificates')->where('status', 'needs_review')->exists()));
    }

    public function test_a_copy_record_is_never_changed_or_removed_by_sync(): void
    {
        [$certificate, $copy] = $this->certificateFromBoard();
        $this->assertCycleOk();
        $original = $this->row('ship', 'vessel_certificate_copies', $copy);

        $this->onCloud(fn () => DB::table('vessel_certificate_copies')->where('sync_uuid', $copy)->update(['sha256' => str_repeat('0', 64), 'updated_at' => now()]));
        $this->syncCycle();
        $this->onCloud(fn () => DB::table('vessel_certificate_copies')->where('sync_uuid', $copy)->delete());
        $this->syncCycle();

        $this->assertSame((array) $original, (array) $this->row('ship', 'vessel_certificate_copies', $copy), 'unchanged on board');
        $notes = $this->onShip(fn () => SyncConflict::query()->where('kind', 'refused')->where('table_name', 'vessel_certificate_copies')->pluck('note')->all());
        $this->assertCount(2, $notes, 'the change and the delete are both refused and flagged');
        foreach ($notes as $note) {
            $this->assertStringContainsString('permanent records', $note);
        }
    }

    public function test_a_damaged_file_on_board_is_not_taken_by_the_office_through_either_record(): void
    {
        [$certificate, $copy] = $this->certificateFromBoard();
        $path = $this->row('ship', 'vessel_certificate_copies', $copy)->path;
        file_put_contents($this->fileOn('ship', $path, 'public'), 'tampered');
        touch($this->fileOn('ship', $path, 'public'), time() + 5);

        $this->assertCycleOk();

        $this->assertNotNull($this->row('cloud', 'vessel_certificate_copies', $copy), 'the records arrive');
        $this->assertNotNull($this->row('cloud', 'vessel_certificates', $certificate));
        $this->assertFileDoesNotExist($this->fileOn('cloud', $path, 'public'), 'the damaged file does not, not even as the certificate\'s own copy');
    }

    public function test_a_copy_whose_file_does_not_match_its_checksum_is_never_put_in_place(): void
    {
        $bytes = $this->pdf();
        $copy = $this->onCloud(function () use ($bytes) {
            $this->as('gm.srd@office.test')->post('/certificate/store', $this->payload('cloud', ['Certificate_Copy' => $this->upload('c.pdf', $bytes)]))->assertOk();

            return (string) DB::table('vessel_certificate_copies')->latest('id')->value('sync_uuid');
        });
        $path = $this->row('cloud', 'vessel_certificate_copies', $copy)->path;
        file_put_contents($this->fileOn('cloud', $path, 'public'), 'tampered');
        touch($this->fileOn('cloud', $path, 'public'), time() + 5);

        $this->assertCycleOk();

        $this->assertNotNull($this->row('ship', 'vessel_certificate_copies', $copy), 'the record arrives');
        $this->assertFileDoesNotExist($this->fileOn('ship', $path, 'public'), 'the damaged file does not');
        $entry = $this->onShip(fn () => app(FileIndex::class)->entry('vessel_certificate_copies', $copy, 'path'));
        $this->assertSame(FileIndex::WANTED, $entry->state, 'tried again later');
        $this->assertStringContainsString('checksum', (string) $entry->last_error);
    }
}
