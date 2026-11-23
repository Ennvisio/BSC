<?php

namespace App\Providers;

use App\Support\Secrets\SecretException;
use App\Support\Secrets\SecretStore;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Fills in credentials from the encrypted SecretStore when the application
 * starts (config/navisuite.php 'secrets.map').
 *
 * They are put into the running configuration only. "config:cache" (and
 * "optimize", "config:show") build or print the configuration in this same
 * process, so for those commands nothing is filled in: the cached file keeps
 * the blank .env values and the secrets never reach bootstrap/cache.
 */
class SecretsServiceProvider extends ServiceProvider
{
    private const CONFIG_WRITERS = ['config:cache', 'optimize', 'config:show'];

    /** Set while a command that writes or prints the configuration runs. */
    public static bool $suppressed = false;

    /**
     * Where each mapped setting came from in this process: store, env,
     * missing, or error (with the reason).
     *
     * @var array<string, array{source: string, error?: string}>
     */
    public static array $sources = [];

    public function register(): void
    {
        $this->app->singleton(SecretStore::class, fn () => SecretStore::fromConfig());

        if (static::$suppressed || $this->writingConfiguration()) {
            static::$sources = [];

            return;
        }

        $this->fillIn();
    }

    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if (in_array($event->command, self::CONFIG_WRITERS, true)) {
                static::$suppressed = true;
            }
        });
    }

    /** Puts every stored secret into its setting. */
    public function fillIn(): void
    {
        static::$sources = [];
        $config = $this->app['config'];
        $store = $this->app->make(SecretStore::class);

        foreach ((array) $config->get('navisuite.secrets.map', []) as $name => $target) {
            $key = $target['config'] ?? null;
            if (! is_string($key) || ! SecretStore::validName((string) $name)) {
                continue;
            }

            try {
                $value = $store->get($name);
            } catch (SecretException $e) {
                static::$sources[$name] = ['source' => 'error', 'error' => $e->getMessage()];

                continue;
            }

            if ($value !== null) {
                $config->set($key, $value);
                static::$sources[$name] = ['source' => 'store'];
            } else {
                static::$sources[$name] = ['source' => filled($config->get($key)) ? 'env' : 'missing'];
            }
        }
    }

    private function writingConfiguration(): bool
    {
        if (! $this->app->runningInConsole()) {
            return false;
        }
        $argv = $_SERVER['argv'] ?? [];

        return isset($argv[0], $argv[1]) && str_ends_with(str_replace('\\', '/', (string) $argv[0]), 'artisan')
            && in_array($argv[1], self::CONFIG_WRITERS, true);
    }
}
