<?php

namespace Tests\Concerns;

use App\Sync\Engine\SyncWorker;
use App\Sync\SyncContext;
use App\Sync\SyncNodes;
use App\Sync\TriggerManager;
use Closure;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Two complete servers in one test: "cloud" and "ship", each with its own
 * SQLite database, the real sync migrations and the real capture triggers.
 *
 * The vessel reaches the cloud through Laravel's HTTP client; the fake
 * routes each request into this application's HTTP kernel while acting as
 * the cloud, so routes, authentication, compression and checksums are all
 * real. $this->network decides what the "satellite link" does with each
 * request (see link()).
 */
trait SyncEngineHarness
{
    protected const CLOUD_URL = 'http://cloud.test';

    protected int $vesselId = 7;

    protected string $cloudNode;

    protected string $vesselNode;

    protected string $vesselToken;

    /** @var list<array{method: string, path: string, status: ?int}> */
    protected array $requests = [];

    /**
     * Per request: 'up' (deliver), 'down' (never arrives), 'drop-response'
     * (the cloud processes it, the answer is lost), or a closure
     * fn (string $path, int $n) => one of those.
     */
    protected string|Closure $network = 'up';

    private string $side = 'none';

    /**
     * Per server, the folders its files live in (Checkpoint 7), e.g.
     * ['cloud' => ['local' => ..., 'public' => ...], 'ship' => [...]].
     * Empty: both servers share the application's folders.
     */
    protected array $fileRoots = [];

    protected function setUpSyncServers(): void
    {
        foreach (['cloud', 'ship'] as $name) {
            config(["database.connections.{$name}" => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ]]);
        }

        foreach (['cloud', 'ship'] as $server) {
            $this->on($server, function () use ($server) {
                $this->buildServerSchema();
                Artisan::call('migrate', [
                    '--database' => $server,
                    '--path' => array_map(fn ($m) => database_path('migrations/'.$m), [
                        '2026_11_21_100000_create_sync_tables.php',
                        '2026_11_21_100100_add_sync_columns_to_synced_tables.php',
                        '2026_11_23_100000_create_sync_engine_tables.php',
                        '2026_11_24_100000_create_sync_merge_tables.php',
                        '2026_11_25_100000_create_sync_file_tables.php',
                        '2026_11_26_100000_create_sync_deletions_table.php',
                        '2026_11_26_130100_add_sync_columns_to_vessel_certificate_copies_table.php',
                    ]),
                    '--realpath' => true,
                    '--force' => true,
                ]);
                app(TriggerManager::class)->install();
            });
        }

        // The cloud registers itself and the vessel; the vessel's .env gets
        // the printed id and token.
        $this->on('cloud', function () {
            $this->cloudNode = app(SyncNodes::class)->register('cloud', null, 'Cloud')['node']->node_id;
            config(['sync.node_id' => $this->cloudNode]);
            $this->seedCloud();
            ['node' => $node, 'token' => $token] = app(SyncNodes::class)->register('vessel', $this->vesselId, 'MV TEST master PC');
            $this->vesselNode = $node->node_id;
            $this->vesselToken = $token;
        });

        // The vessel starts as a copy of the cloud (what the bootstrap
        // checkpoint will do): same rows, same UUIDs.
        foreach ($this->bootstrapTables() as $table) {
            $this->copyToShip($table);
        }
        $this->afterBootstrap();
        $this->on('cloud', fn () => DB::table('sync_outbox')->delete());
        $this->on('cloud', fn () => app(\App\Sync\Engine\OutboxSequencer::class)->sequence());

        $this->network = 'up';
        $this->requests = [];
        Http::fake(fn (ClientRequest $request) => $this->link($request));
        Http::preventStrayRequests();
    }

    /** What the cloud holds before the vessel is set up. */
    protected function seedCloud(): void
    {
        DB::table('vessels')->insert(['id' => $this->vesselId, 'name' => 'MV TEST', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('vessels')->insert(['id' => 8, 'name' => 'MV OTHER', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->insert(['id' => 1, 'name' => 'Office', 'email' => 'office@example.test', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->insert(['id' => 2, 'name' => 'Master', 'email' => 'master@example.test', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('surveys')->insert(['id' => 1, 'name' => 'Annual', 'status' => 1, 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return list<string> tables copied to the vessel when it is set up */
    protected function bootstrapTables(): array
    {
        return ['vessels', 'users', 'surveys', 'currencies', 'ranks', 'categories'];
    }

    protected function afterBootstrap(): void
    {
    }

    /** Runs $callback as the given server: its database and its .env. */
    protected function on(string $server, callable $callback): mixed
    {
        $previous = $this->side;
        $this->become($server);

        try {
            return $callback();
        } finally {
            $this->become($previous);
        }
    }

    protected function onCloud(callable $callback): mixed
    {
        return $this->on('cloud', $callback);
    }

    protected function onShip(callable $callback): mixed
    {
        return $this->on('ship', $callback);
    }

    /** One sync cycle on the vessel, the way the service runs it. */
    protected function syncCycle(): array
    {
        return $this->onShip(fn () => app(SyncWorker::class)->runCycle());
    }

    protected function copyToShip(string $table): void
    {
        $rows = $this->onCloud(fn () => DB::table($table)->get()->map(fn ($r) => (array) $r)->all());
        $this->onShip(fn () => SyncContext::withoutCapture(function () use ($table, $rows) {
            DB::table($table)->delete();
            foreach ($rows as $row) {
                DB::table($table)->insert($row);
            }
        }));
    }

    protected function row(string $server, string $table, string $uuid): ?object
    {
        return $this->on($server, fn () => DB::table($table)->where('sync_uuid', $uuid)->first());
    }

    protected function uuidOf(string $server, string $table, int $id): string
    {
        return $this->on($server, fn () => DB::table($table)->where('id', $id)->value('sync_uuid'));
    }

    private function become(string $server): void
    {
        $this->side = $server;

        if (isset($this->fileRoots[$server])) {
            config([
                'filesystems.disks.local.root' => $this->fileRoots[$server]['local'],
                'filesystems.disks.public_images.root' => $this->fileRoots[$server]['public'],
            ]);
            \Illuminate\Support\Facades\Storage::forgetDisk(['local', 'public_images']);
        }

        if ($server === 'cloud') {
            config([
                'navisuite.mode' => 'cloud', 'navisuite.vessel_id' => null,
                'sync.node_id' => $this->cloudNode ?? null, 'sync.node_token' => null, 'sync.cloud_url' => null,
            ]);
            DB::setDefaultConnection('cloud');
        } elseif ($server === 'ship') {
            config([
                'navisuite.mode' => 'vessel', 'navisuite.vessel_id' => $this->vesselId,
                'sync.node_id' => $this->vesselNode ?? null, 'sync.node_token' => $this->vesselToken ?? null,
                'sync.cloud_url' => self::CLOUD_URL,
            ]);
            DB::setDefaultConnection('ship');
        } else {
            config(['navisuite.mode' => 'cloud', 'navisuite.vessel_id' => null, 'sync.node_id' => null, 'sync.node_token' => null, 'sync.cloud_url' => null]);
            DB::setDefaultConnection('sqlite');
        }
    }

    /** The satellite link: delivers the request to the cloud's HTTP kernel, or not. */
    private function link(ClientRequest $request)
    {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $n = count(array_filter($this->requests, fn ($r) => $r['path'] === $path)) + 1;
        $mode = $this->network instanceof Closure ? ($this->network)($path, $n) : $this->network;

        if ($mode === 'down') {
            $this->requests[] = ['method' => $request->method(), 'path' => $path, 'status' => null];

            return Http::failedConnection('Satellite link down');
        }

        $response = $this->onCloud(function () use ($request) {
            $server = [];
            foreach ($request->headers() as $name => $values) {
                $key = strtoupper(str_replace('-', '_', $name));
                $server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_'.$key] = implode(', ', $values);
            }
            $incoming = Request::create($request->url(), $request->method(), [], [], [], $server, $request->body());

            return app(HttpKernel::class)->handle($incoming);
        });

        $this->requests[] = ['method' => $request->method(), 'path' => $path, 'status' => $response->getStatusCode()];

        if ($mode === 'drop-response') {
            return Http::failedConnection('Link dropped before the answer arrived');
        }
        if ($mode !== 'up') {
            throw new RuntimeException("Unknown network mode {$mode}.");
        }

        $headers = array_map(fn ($v) => implode(', ', $v), $response->headers->all());

        // A real HTTP client undoes the compression; the fake does not.
        return Http::response($response->getContent(), $response->getStatusCode(), $headers);
    }

    protected function buildServerSchema(): void
    {
        Schema::create('vessels', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('vessel_id')->nullable();
            $t->string('role')->nullable();
            $t->timestamps();
        });
        Schema::create('surveys', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('status')->default(true);
            $t->integer('created_by');
            $t->integer('updated_by')->nullable();
            $t->timestamps();
        });
        Schema::create('currencies', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->char('code', 3)->unique();
            $t->string('name', 60);
            $t->string('symbol', 8)->nullable();
            $t->boolean('is_top')->default(false);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
        Schema::create('ranks', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique();
            $t->string('name');
            $t->string('department')->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('symbol')->unique();
            $t->string('created_by');
            $t->string('updated_by')->nullable();
            $t->boolean('status')->default(false);
            $t->timestamps();
            $t->boolean('is_catalog')->default(false);
        });
        Schema::create('manufacturers', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('budget_groups', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('status')->default(true);
            $t->string('created_by');
            $t->string('updated_by')->nullable();
            $t->timestamps();
        });
        Schema::create('item_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('item_groups')->cascadeOnDelete();
            $t->string('name');
            $t->string('path');
            $t->timestamps();
            $t->unsignedBigInteger('category_id')->nullable();
            $t->unique(['category_id', 'parent_id', 'name']);
        });
        Schema::create('vessel_surveys', function (Blueprint $t) {
            $t->id();
            $t->integer('vessel_id');
            $t->integer('survey_id');
            $t->string('society_name');
            $t->date('survey_date');
            $t->date('survey_exp_date');
            $t->boolean('status')->default(true);
            $t->integer('created_by');
            $t->integer('updated_by')->nullable();
            $t->timestamps();
        });
    }

    protected function newUuid(): string
    {
        return (string) Str::uuid();
    }
}
