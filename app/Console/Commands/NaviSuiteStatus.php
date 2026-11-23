<?php

namespace App\Console\Commands;

use App\Support\AppMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reports which deployment this server is (cloud or vessel), the services
 * whose configuration differs between the two, and anything misconfigured.
 * Read-only: it never writes to the database or to any file.
 *
 * Exits with a failure code when there are errors, so an installer or a
 * health check can call it.
 */
class NaviSuiteStatus extends Command
{
    protected $signature = 'navisuite:status {--skip-db : Do not connect to the database}';

    protected $description = 'Show the deployment mode (cloud or vessel), environment-specific services and configuration problems';

    private const LOOPBACK_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    public function handle(AppMode $mode): int
    {
        $this->errors = $mode->configurationErrors();
        $this->warnings = [];

        $connection = (string) config('database.default');
        $db = (array) config("database.connections.{$connection}", []);
        $mailDriver = (string) (config('mail.driver') ?? config('mail.default'));

        $this->table(['Setting', 'Value'], [
            ['Mode', $mode->mode()],
            ['Vessel id', $mode->vesselId() ?? '-'],
            ['Support accounts', count($mode->supportAccounts())],
            ['Database connection', $connection],
            ['Database driver', $db['driver'] ?? '-'],
            ['Database host', $db['host'] ?? '-'],
            ['Database name', $db['database'] ?? '-'],
            ['Cache store', config('cache.default')],
            ['Session driver', config('session.driver')],
            ['Queue connection', config('queue.default')],
            ['Mail driver', $mailDriver],
            ['Log channel', config('logging.default')],
            ['Filesystem disk', config('filesystems.default')],
            ['App URL', config('app.url')],
            ['Debug', config('app.debug') ? 'on' : 'off'],
        ]);

        if ($this->errors === []) {
            $mode->isVessel()
                ? $this->checkVesselConfiguration($connection, $db, $mailDriver)
                : $this->checkCloudConfiguration($mode);

            if (! $this->option('skip-db')) {
                $this->checkDatabase($mode);
            }
        }

        foreach ($this->warnings as $warning) {
            $this->warn('WARNING: '.$warning);
        }
        foreach ($this->errors as $error) {
            $this->error('ERROR: '.$error);
        }

        if ($this->errors !== []) {
            return self::FAILURE;
        }

        $this->info('NaviSuite is configured for '.$mode->mode().' mode.');

        return self::SUCCESS;
    }

    private function checkVesselConfiguration(string $connection, array $db, string $mailDriver): void
    {
        if ($connection !== 'vessel') {
            $this->warnings[] = "The database connection is \"{$connection}\". A vessel server should use the \"vessel\" connection (DB_CONNECTION=vessel).";
        }

        if (($db['driver'] ?? null) === 'mysql' && ! in_array($db['host'] ?? '', self::LOOPBACK_HOSTS, true)) {
            $this->warnings[] = 'The database host is not the local machine. On a vessel server MySQL should listen on 127.0.0.1 only.';
        }

        if ($mailDriver === 'smtp') {
            $this->warnings[] = 'Mail uses SMTP, which needs internet access. Use MAIL_DRIVER=log on a vessel server.';
        }

        if (config('app.debug')) {
            $this->warnings[] = 'APP_DEBUG is on. Every PC on the ship\'s LAN would see error details.';
        }

        // Checkpoint 7: credentials belong in the encrypted store.
        $secrets = app(\App\Support\Secrets\SecretsReport::class)->problems();
        array_push($this->errors, ...$secrets['errors']);
        array_push($this->warnings, ...$secrets['warnings']);
    }

    private function checkCloudConfiguration(AppMode $mode): void
    {
        if ($mode->vesselId() !== null) {
            $this->warnings[] = 'APP_VESSEL_ID is set but is ignored in cloud mode.';
        }

        if ($mode->supportAccounts() !== []) {
            $this->warnings[] = 'APP_SUPPORT_ACCOUNTS is set but is ignored in cloud mode.';
        }
    }

    private function checkDatabase(AppMode $mode): void
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->errors[] = 'Cannot connect to the database: '.$e->getMessage();

            return;
        }

        if (! Schema::hasTable('vessels')) {
            $this->errors[] = 'The database has no vessels table. Load the vessel snapshot or run the migrations first.';

            return;
        }

        $hasOfflineColumn = Schema::hasColumn('vessels', 'offline_enabled_at');
        if (! $hasOfflineColumn) {
            $this->warnings[] = 'The vessels.offline_enabled_at column is missing. Run "php artisan migrate" to add it.';
        }

        if ($mode->isVessel()) {
            $name = DB::table('vessels')->where('id', $mode->vesselId())->value('name');

            if ($name === null) {
                $this->errors[] = "APP_VESSEL_ID is {$mode->vesselId()}, but no vessel with that id exists in this database.";
            } else {
                $this->line("This server belongs to vessel #{$mode->vesselId()}: {$name}");
            }

            return;
        }

        if ($hasOfflineColumn) {
            $count = DB::table('vessels')->whereNotNull('offline_enabled_at')->count();
            $this->line("Vessels running their own onboard server: {$count}");
        }
    }
}
