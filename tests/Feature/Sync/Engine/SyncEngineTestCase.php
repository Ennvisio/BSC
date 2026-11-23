<?php

namespace Tests\Feature\Sync\Engine;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Concerns\SyncEngineHarness;
use Tests\TestCase;

/**
 * Base for the sync engine tests: a cloud and a vessel server (see
 * SyncEngineHarness), with the pilot tables enabled and no random jitter,
 * so retry times are exact.
 */
abstract class SyncEngineTestCase extends TestCase
{
    use SyncEngineHarness;

    /** Set to a closure fn (QueryExecuted $q): bool to make that query "crash" the process. */
    protected ?\Closure $crashWhen = null;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sync.enabled_tables' => 'currencies,ranks,categories,manufacturers,budget_groups,item_groups,vessel_surveys',
            'sync.worker.jitter' => 0,
        ]);
        $this->setUpSyncServers();

        Event::listen(QueryExecuted::class, function (QueryExecuted $q) {
            if ($this->crashWhen && ($this->crashWhen)($q)) {
                throw new RuntimeException('Simulated power cut after: '.$q->sql);
            }
        });
    }

    protected function addManufacturer(string $server, string $name, string $updatedAt = '2026-11-20 08:00:00'): string
    {
        return $this->on($server, function () use ($name, $updatedAt) {
            $id = DB::table('manufacturers')->insertGetId(['name' => $name, 'created_at' => $updatedAt, 'updated_at' => $updatedAt]);

            return DB::table('manufacturers')->where('id', $id)->value('sync_uuid');
        });
    }

    protected function renameManufacturer(string $server, string $uuid, string $name, string $updatedAt): void
    {
        $this->on($server, fn () => DB::table('manufacturers')->where('sync_uuid', $uuid)->update(['name' => $name, 'updated_at' => $updatedAt]));
    }

    protected function addCurrency(string $code, string $name): string
    {
        return $this->onCloud(function () use ($code, $name) {
            $id = DB::table('currencies')->insertGetId(['code' => $code, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('currencies')->where('id', $id)->value('sync_uuid');
        });
    }

    protected function assertCycleOk(): array
    {
        $result = $this->syncCycle();
        $this->assertTrue($result['ok'], 'Sync cycle failed: '.json_encode($result));

        return $result;
    }

    /** Both servers hold exactly the same rows (by UUID) with the same values. */
    protected function assertConverged(string $table, array $columns): void
    {
        $read = fn () => DB::table($table)->orderBy('sync_uuid')->get(['sync_uuid', ...$columns])->map(fn ($r) => (array) $r)->all();
        $this->assertSame($this->onCloud($read), $this->onShip($read), "{$table} differs between cloud and vessel.");
    }

    /**
     * Calls the cloud's sync API directly, as a vessel would.
     *
     * @param  array<string, string|null>  $headers  overrides; null removes a header
     */
    protected function api(string $method, string $path, ?array $body = null, array $headers = [], ?string $rawBody = null, bool $alreadyEncoded = false): TestResponse
    {
        $json = $rawBody ?? ($body === null ? '' : json_encode($body));
        $defaults = [
            'Authorization' => 'Bearer '.$this->vesselToken,
            'X-Sync-Node' => $this->vesselNode,
            'X-Sync-Protocol' => '1',
            'Accept' => 'application/json',
        ];
        if ($body !== null || $rawBody !== null) {
            $defaults += ['Content-Type' => 'application/json', 'Content-Encoding' => 'gzip', 'X-Sync-Body-SHA256' => hash('sha256', $json)];
            $json = $alreadyEncoded ? $json : gzencode($json);
        }

        $server = [];
        foreach (array_merge($defaults, $headers) as $name => $value) {
            if ($value === null) {
                continue;
            }
            $key = strtoupper(str_replace('-', '_', $name));
            $server[in_array($key, ['CONTENT_TYPE'], true) ? $key : 'HTTP_'.$key] = $value;
        }

        return $this->onCloud(fn () => $this->call($method, '/api/sync/v1/'.ltrim($path, '/'), [], [], [], $server, $json));
    }

    protected function batch(array $events, ?string $batchId = null, int $from = 1, int $to = 1): array
    {
        return ['protocol' => 1, 'batch_id' => $batchId ?? $this->newUuid(), 'seq_from' => $from, 'seq_to' => $to, 'events' => $events];
    }

    protected function event(string $table, string $rowUuid, ?array $values, string $op = 'insert', ?int $version = 1): array
    {
        return ['event_id' => $this->newUuid(), 'seq' => 1, 'table' => $table, 'row_uuid' => $rowUuid, 'op' => $op, 'version' => $version, 'vessel_id' => null, 'values' => $values];
    }

    protected function cloudCount(string $table): int
    {
        return $this->onCloud(fn () => DB::table($table)->count());
    }

    protected function shipCount(string $table): int
    {
        return $this->onShip(fn () => DB::table($table)->count());
    }
}
