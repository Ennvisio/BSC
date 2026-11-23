<?php

namespace Tests\Feature\Sync;

use App\Sync\SyncInbox;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\Concerns\BuildsSyncSchema;
use Tests\TestCase;

/** sync_inbox stores each received event exactly once. */
class SyncInboxTest extends TestCase
{
    use BuildsRequisitionSchema;
    use BuildsSyncSchema;

    private const SOURCE = '22222222-2222-4222-8222-222222222222';

    private SyncInbox $inbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
        $this->runSyncMigrations();
        $this->inbox = app(SyncInbox::class);
    }

    private function event(array $overrides = []): array
    {
        return $overrides + [
            'event_id' => (string) Str::uuid(),
            'table_name' => 'orders',
            'row_uuid' => (string) Str::uuid(),
            'operation' => 'update',
            'row_version' => 3,
            'vessel_id' => $this->vesselId,
            'payload' => ['status' => 'approved'],
        ];
    }

    public function test_an_event_is_stored_once_and_a_repeat_is_recognised(): void
    {
        $event = $this->event();

        $this->assertTrue($this->inbox->receive(self::SOURCE, $event));
        $this->assertFalse($this->inbox->receive(self::SOURCE, $event));
        $this->assertFalse($this->inbox->receive(self::SOURCE, ['event_id' => strtoupper($event['event_id'])] + $event));

        $rows = DB::table('sync_inbox')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('pending', $rows[0]->status);
        $this->assertSame(['status' => 'approved'], json_decode($rows[0]->payload, true));
        $this->assertSame(self::SOURCE, $rows[0]->source_node);
    }

    public function test_the_database_itself_refuses_a_repeated_event_id(): void
    {
        $event = $this->event();
        $this->inbox->receive(self::SOURCE, $event);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('sync_inbox')->insert([
            'event_id' => $event['event_id'], 'source_node' => self::SOURCE, 'table_name' => 'orders',
            'row_uuid' => $event['row_uuid'], 'operation' => 'update', 'status' => 'pending',
        ]);
    }

    public function test_a_batch_reports_new_and_repeated_events(): void
    {
        $seen = $this->event();
        $this->inbox->receive(self::SOURCE, $seen);
        $new = $this->event();

        $result = $this->inbox->receiveBatch(self::SOURCE, [$seen, $new, $new]);

        $this->assertSame([$new['event_id']], $result['accepted']);
        $this->assertSame([$seen['event_id'], $new['event_id']], $result['duplicates']);
        $this->assertSame(2, DB::table('sync_inbox')->count());
    }

    public function test_one_malformed_event_rejects_the_whole_batch(): void
    {
        try {
            $this->inbox->receiveBatch(self::SOURCE, [$this->event(), $this->event(['operation' => 'truncate'])]);
            $this->fail('The batch should have been rejected.');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame(0, DB::table('sync_inbox')->count());
    }

    public function test_a_repeat_inside_a_larger_transaction_does_not_undo_the_rest(): void
    {
        $event = $this->event();
        $this->inbox->receive(self::SOURCE, $event);

        DB::transaction(function () use ($event) {
            DB::table('budget_groups')->insert(['name' => 'Same transaction', 'status' => true, 'created_by' => 1]);
            $this->assertFalse($this->inbox->receive(self::SOURCE, $event));
        });

        $this->assertTrue(DB::table('budget_groups')->where('name', 'Same transaction')->exists());
    }

    public static function malformedEvents(): array
    {
        return [
            'event id not a uuid' => [['event_id' => 'abc']],
            'unknown table' => [['table_name' => 'password_resets']],
            'row uuid missing' => [['row_uuid' => null]],
            'unknown operation' => [['operation' => 'upsert']],
            'version zero' => [['row_version' => 0]],
            'version not a number' => [['row_version' => '3']],
            'vessel not a number' => [['vessel_id' => 'one']],
            'payload not an object' => [['payload' => 'status=approved']],
        ];
    }

    #[DataProvider('malformedEvents')]
    public function test_malformed_events_are_rejected(array $overrides): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->inbox->receive(self::SOURCE, $this->event($overrides));
    }

    public function test_the_source_must_be_a_node_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->inbox->receive('vessel-1', $this->event());
    }
}
