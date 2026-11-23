<?php

namespace App\Console\Commands;

use App\Support\Secrets\SecretException;
use App\Support\Secrets\SecretsReport;
use App\Support\Secrets\SecretStore;
use Illuminate\Console\Command;

/**
 * Manages the encrypted credentials (App\Support\Secrets\SecretStore).
 *
 * A value is never taken from the command line, where it would stay in the
 * shell history and be visible to other users in the process list: it is
 * typed at a hidden prompt, or piped in with --stdin (the installer).
 * Nothing here prints a secret's value.
 */
class NaviSuiteSecrets extends Command
{
    protected $signature = 'navisuite:secrets
        {action : status, set, forget, check or import-env}
        {name? : The secret, for set and forget (see config/navisuite.php secrets.map)}
        {--stdin : set: read the value from standard input instead of a prompt}
        {--keep-env : import-env: leave the values in .env as well}
        {--env-file= : import-env: the .env file (default: the application\'s)}';

    protected $description = 'Store, check and list the encrypted credentials (sync token, database password, ...)';

    public function handle(SecretStore $store, SecretsReport $report): int
    {
        try {
            return match ($this->argument('action')) {
                'status' => $this->status($store, $report),
                'set' => $this->set($store),
                'forget' => $this->forget($store),
                'check' => $this->check($store),
                'import-env' => $this->importEnv($store),
                default => $this->refuse('Unknown action. Use status, set, forget, check or import-env.'),
            };
        } catch (SecretException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function status(SecretStore $store, SecretsReport $report): int
    {
        $protector = $store->protector();
        $this->line('Folder:    '.$store->path());
        $this->line('Protector: '.$protector->name().($protector->available() ? '' : ' (not available: '.$protector->unavailableReason().')'));
        $this->newLine();

        $labels = ['store' => 'encrypted store', 'env' => '.env (plaintext)', 'missing' => 'not set', 'error' => 'ERROR', 'unknown' => '?'];
        $this->table(['Secret', 'Setting', 'Comes from', 'Protector'], array_map(fn ($row) => [
            $row['name'], $row['setting'], $labels[$row['source']] ?? $row['source'], $store->protectorOf($row['name']) ?? '',
        ], $report->rows()));

        $problems = $report->problems();
        foreach ($problems['errors'] as $error) {
            $this->error($error);
        }
        foreach ($problems['warnings'] as $warning) {
            $this->warn($warning);
        }

        return $problems['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function set(SecretStore $store): int
    {
        $name = $this->knownName();
        if ($this->option('stdin')) {
            $value = rtrim((string) stream_get_contents(STDIN), "\r\n");
        } else {
            $value = (string) $this->secret("Value for {$name} (not shown)");
            $again = (string) $this->secret('Once more, to check');
            if (! hash_equals($value, $again)) {
                return $this->refuse('The two values differ. Nothing was stored.');
            }
        }

        $store->put($name, $value);
        $this->info("Stored {$name} ({$store->protector()->name()}).");
        $this->line('Restart the NaviSuite services (or the web server) so they use it.');

        return self::SUCCESS;
    }

    private function forget(SecretStore $store): int
    {
        $name = $this->knownName();
        $store->forget($name)
            ? $this->info("Removed {$name}.")
            : $this->line("{$name} was not stored.");

        return self::SUCCESS;
    }

    private function check(SecretStore $store): int
    {
        $failed = 0;
        foreach ($store->names() as $name) {
            try {
                $store->get($name);
                $this->line("<info>OK</info>    {$name}");
            } catch (SecretException $e) {
                $failed++;
                $this->line("<error>FAIL</error>  {$name}: {$e->getMessage()}");
            }
        }
        if ($store->names() === []) {
            $this->line('No secrets are stored.');
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Moves every mapped value that is set in .env into the store and
     * blanks the line in .env (unless --keep-env). Safe to run again.
     */
    private function importEnv(SecretStore $store): int
    {
        $file = (string) ($this->option('env-file') ?: app()->environmentFilePath());
        if (! is_file($file)) {
            return $this->refuse("{$file} does not exist.");
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $moved = [];

        foreach ((array) config('navisuite.secrets.map', []) as $name => $target) {
            $env = $target['env'] ?? null;
            if (! is_string($env) || $env === '') {
                continue;
            }
            foreach ($lines as $i => $line) {
                if (! preg_match('/^\s*'.preg_quote($env, '/').'\s*=(.*)$/', $line, $m)) {
                    continue;
                }
                $value = $this->envValue($m[1]);
                if ($value === '') {
                    continue;
                }
                $store->put($name, $value);
                if (! $store->get($name) || ! hash_equals($value, (string) $store->get($name))) {
                    return $this->refuse("Could not read {$name} back. .env was not changed.");
                }
                if (! $this->option('keep-env')) {
                    $lines[$i] = $env.'=';
                }
                $moved[] = "{$env} -> {$name}";
            }
        }

        if ($moved === []) {
            $this->line('Nothing to move: no mapped value is set in '.$file.'.');

            return self::SUCCESS;
        }
        if (! $this->option('keep-env')) {
            SecretStore::writePrivateFile($file, implode(PHP_EOL, $lines).PHP_EOL);
        }
        foreach ($moved as $line) {
            $this->info('Moved '.$line);
        }
        $this->line('Run "php artisan config:cache" again if the configuration is cached, and restart the NaviSuite services.');

        return self::SUCCESS;
    }

    private function envValue(string $raw): string
    {
        $value = trim($raw);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            return substr($value, 1, -1);
        }

        return preg_replace('/\s+#.*$/', '', $value);
    }

    private function knownName(): string
    {
        $name = (string) $this->argument('name');
        if (! array_key_exists($name, (array) config('navisuite.secrets.map', []))) {
            throw new SecretException('Name one of: '.implode(', ', array_keys((array) config('navisuite.secrets.map', []))).'.');
        }

        return $name;
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
