<?php

namespace App\Support;

use App\Sync\SyncRegistry;
use App\Sync\TriggerBuilder;
use App\Sync\TriggerManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The checks behind GET /health.
 *
 * Critical checks decide whether the server can serve users at all (status
 * "down", HTTP 503). The others report a background service or capacity
 * problem (status "degraded", HTTP 200) that needs attention but does not
 * stop the crew working.
 */
class HealthCheck
{
    public const OK = 'ok';

    public const DEGRADED = 'degraded';

    public const DOWN = 'down';

    public function __construct(private AppMode $mode)
    {
    }

    /**
     * @return array{status: string, vessel: ?array, checks: array<string, array>}
     */
    public function run(): array
    {
        $vessel = null;
        $checks = [];

        $checks['configuration'] = $this->check(true, function () {
            $errors = $this->mode->configurationErrors();

            return [$errors === [], $errors === [] ? 'Mode '.$this->mode->mode().'.' : implode(' ', $errors)];
        });

        $checks['database'] = $this->check(true, function () use (&$vessel) {
            DB::select('SELECT 1');

            if (! $this->mode->isVessel()) {
                return [true, 'Connected.'];
            }

            $name = DB::table('vessels')->where('id', $this->mode->vesselId())->value('name');
            $vessel = $name === null ? null : ['id' => $this->mode->vesselId(), 'name' => $name];

            return $name === null
                ? [false, 'Connected, but vessel '.$this->mode->vesselId().' is not in the database.']
                : [true, 'Connected.'];
        });

        $checks['storage'] = $this->check(true, function () {
            $paths = ['app' => storage_path('app'), 'logs' => storage_path('logs'), 'cache' => storage_path('framework/cache')];
            $notWritable = array_keys(array_filter($paths, fn ($path) => ! is_dir($path) || ! is_writable($path)));

            return [$notWritable === [], $notWritable === [] ? 'Writable.' : 'Not writable: '.implode(', ', $notWritable).'.'];
        });

        $checks['php'] = $this->check(true, function () {
            $missing = array_values(array_filter(
                (array) config('navisuite.health.required_extensions', []),
                fn ($extension) => ! extension_loaded($extension)
            ));

            return [$missing === [], $missing === [] ? 'PHP '.PHP_VERSION.'.' : 'Missing extensions: '.implode(', ', $missing).'.'];
        });

        $checks['disk'] = $this->check(false, function () {
            $free = @disk_free_space(storage_path());
            $freeMb = $free === false ? null : (int) floor($free / 1048576);
            $minimum = (int) config('navisuite.health.min_free_disk_mb', 2048);

            if ($freeMb === null) {
                return [false, 'Free space unknown.'];
            }

            return [$freeMb >= $minimum, "{$freeMb} MB free.", ['free_mb' => $freeMb]];
        });

        if ($this->mode->isVessel()) {
            $checks['scheduler'] = $this->check(false, fn () => $this->heartbeat(Heartbeat::SCHEDULER, 'navisuite.health.scheduler_max_age', 'NaviSuite-Scheduler'));
            $checks['queue'] = $this->check(false, fn () => $this->queue());
            if (config('navisuite.discovery.enabled') !== false) {
                $checks['discovery'] = $this->check(false, fn () => $this->heartbeat(Heartbeat::DISCOVERY, 'navisuite.health.discovery_max_age', 'NaviSuite-Discovery'));
            }
            // Only once sync has been set up for this vessel.
            if (filled(config('sync.cloud_url'))) {
                $checks['sync'] = $this->check(false, fn () => $this->sync());
            }
        }

        if ($this->tableExists('sync_outbox')) {
            $checks['sync_capture'] = $this->check(false, fn () => $this->syncCapture());
        }

        // Checkpoint 7: what needs a person (App\Sync\SyncHealth).
        if ($this->tableExists('sync_state') && (filled(config('sync.node_id')) || $this->mode->isVessel())) {
            $checks['sync_alerts'] = $this->check(false, function () {
                $alerts = app(\App\Sync\SyncHealth::class)->alerts();
                $level = \App\Sync\SyncHealth::level($alerts);
                $serious = array_values(array_filter($alerts, fn ($a) => $a['level'] !== \App\Sync\SyncHealth::INFO));

                return [$level === 'ok', $serious === [] ? 'Nothing needs attention.' : implode(' ', array_column($serious, 'message')),
                    ['level' => $level, 'alerts' => count($serious)]];
            });
        }

        return ['status' => $this->overall($checks), 'vessel' => $vessel, 'checks' => $checks];
    }

    /** Runs one check, turning any exception into a failed result. */
    private function check(bool $critical, callable $probe): array
    {
        try {
            [$ok, $message, $extra] = array_pad($probe(), 3, []);
        } catch (Throwable $e) {
            [$ok, $message, $extra] = [false, class_basename($e).': '.$e->getMessage(), []];
        }

        return ['ok' => (bool) $ok, 'critical' => $critical, 'message' => $message] + $extra;
    }

    private function heartbeat(string $name, string $maxAgeKey, string $service): array
    {
        $age = Heartbeat::age($name);
        $max = (int) config($maxAgeKey);

        if ($age === null) {
            return [false, "No heartbeat yet. Is the {$service} service running?", ['age_seconds' => null]];
        }

        return [$age <= $max, $age <= $max ? "Last seen {$age}s ago." : "Last seen {$age}s ago. Is the {$service} service running?", ['age_seconds' => $age]];
    }

    private function queue(): array
    {
        if (config('queue.default') === 'sync') {
            return [true, 'Jobs run immediately (QUEUE_CONNECTION=sync).'];
        }

        [$ok, $message, $extra] = $this->heartbeat(Heartbeat::QUEUE, 'navisuite.health.queue_max_age', 'NaviSuite-Queue');

        if ($this->tableExists('jobs')) {
            $extra['pending'] = DB::table('jobs')->count();
        }
        if ($this->tableExists('failed_jobs')) {
            $extra['failed'] = DB::table('failed_jobs')->count();
        }

        return [$ok, $message, $extra];
    }

    /**
     * The sync service is running and set up. Being offline is normal at sea
     * and is not a failure here; the time since the last successful sync is
     * reported so a monitor can decide.
     */
    private function sync(): array
    {
        $problems = app(\App\Sync\Engine\SyncAgent::class)->configurationProblems();
        if ($problems !== []) {
            return [false, 'Not set up: '.implode(' ', $problems)];
        }

        [$ok, $message, $extra] = $this->heartbeat(Heartbeat::SYNC, 'navisuite.health.sync_max_age', 'NaviSuite-Sync');
        $worker = app(\App\Sync\Engine\SyncWorker::class)->status();
        $extra['last_success_age_seconds'] = $worker['last_success_at'] ? max(0, time() - $worker['last_success_at']) : null;
        $extra['failures_in_a_row'] = $worker['failures'];
        if ($ok && $worker['failures'] > 0) {
            $message .= " Last cycle failed: {$worker['last_error']}";
        }

        return [$ok, $message, $extra];
    }

    private function syncCapture(): array
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            return [true, 'Not checked on '.$driver.'.'];
        }

        // One query, rather than inspecting all 56 tables on every call.
        $builder = app(TriggerBuilder::class);
        $expected = array_sum(array_map(
            fn ($table) => count($builder->triggerNames($driver, $table)),
            app(SyncRegistry::class)->tableNames()
        ));
        $installed = count(app(TriggerManager::class)->installedTriggerNames());

        return [$installed >= $expected, $installed >= $expected
            ? 'Changes are being recorded.'
            : "{$installed} of {$expected} capture triggers installed. Run \"php artisan sync:triggers install\".",
            ['triggers' => $installed, 'expected' => $expected]];
    }

    private function overall(array $checks): string
    {
        foreach ($checks as $check) {
            if (! $check['ok'] && $check['critical']) {
                return self::DOWN;
            }
        }

        foreach ($checks as $check) {
            if (! $check['ok']) {
                return self::DEGRADED;
            }
        }

        return self::OK;
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
