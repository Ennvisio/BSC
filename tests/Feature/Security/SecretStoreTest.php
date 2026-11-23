<?php

namespace Tests\Feature\Security;

use App\Providers\SecretsServiceProvider;
use App\Support\Secrets\DpapiProtector;
use App\Support\Secrets\SecretException;
use App\Support\Secrets\SecretStore;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

/**
 * Credentials are kept encrypted (Checkpoint 7): never in plaintext in the
 * store's files, never in the configuration cache, never printed.
 */
class SecretStoreTest extends TestCase
{
    private const TOKEN = 'node-token-4f1c2b9e7d8a6f5e3c1b';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/secrets-'.bin2hex(random_bytes(4)));
        config(['navisuite.secrets.path' => $this->dir, 'navisuite.secrets.protector' => 'auto']);
        $this->app->forgetInstance(SecretStore::class);
        SecretsServiceProvider::$suppressed = false;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        SecretsServiceProvider::$suppressed = false;
        SecretsServiceProvider::$sources = [];
        parent::tearDown();
    }

    private function store(): SecretStore
    {
        return app(SecretStore::class);
    }

    public function test_a_secret_is_stored_encrypted_and_read_back(): void
    {
        $this->store()->put('sync_node_token', self::TOKEN);

        $file = $this->dir.'/sync_node_token.secret';
        $raw = (string) file_get_contents($file);
        $this->assertStringNotContainsString(self::TOKEN, $raw, 'never in plaintext on disk');
        $this->assertStringNotContainsString(base64_encode(self::TOKEN), $raw);
        $this->assertStringStartsWith('NSSEC1:sodium:', $raw, 'off Windows: the key-file protector');
        if (DIRECTORY_SEPARATOR === '/') {
            $this->assertSame('0600', substr(sprintf('%o', fileperms($file)), -4), 'owner only');
            $this->assertSame('0600', substr(sprintf('%o', fileperms($this->dir.'/secrets.key')), -4));
        }

        $this->app->forgetInstance(SecretStore::class);
        $this->assertSame(self::TOKEN, $this->store()->get('sync_node_token'));
    }

    public function test_a_secret_cannot_be_opened_with_another_key_or_when_damaged(): void
    {
        $this->store()->put('db_password', 'database-secret');
        file_put_contents($this->dir.'/secrets.key', base64_encode(random_bytes(32))."\n");
        $this->app->forgetInstance(SecretStore::class);

        try {
            $this->store()->get('db_password');
            $this->fail('opened with the wrong key');
        } catch (SecretException $e) {
            $this->assertStringContainsString('could not be opened', $e->getMessage());
        }

        file_put_contents($this->dir.'/db_password.secret', 'NSSEC1:sodium:'.base64_encode('short'));
        $this->app->forgetInstance(SecretStore::class);
        $this->expectException(SecretException::class);
        $this->store()->get('db_password');
    }

    public function test_secret_names_cannot_reach_outside_the_folder(): void
    {
        foreach (['../.env', 'a/b', 'APP_KEY', '', 'x'] as $name) {
            try {
                $this->store()->put($name, 'v');
                $this->fail("accepted {$name}");
            } catch (SecretException) {
                $this->assertFileDoesNotExist(dirname($this->dir).'/.env.secret');
            }
        }
    }

    public function test_dpapi_is_only_offered_where_it_works(): void
    {
        $reason = (new DpapiProtector)->unavailableReason();
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Covered on the Windows server itself (navisuite:secrets check).');
        }
        $this->assertSame('DPAPI exists on Windows only.', $reason);
        $this->assertSame('sodium', $this->store()->protector()->name());
    }

    public function test_stored_secrets_fill_in_the_settings_and_win_over_env(): void
    {
        $this->store()->put('sync_node_token', self::TOKEN);
        config(['sync.node_token' => 'plaintext-from-env', 'navisuite.health.token' => 'health-from-env']);

        (new SecretsServiceProvider($this->app))->fillIn();

        $this->assertSame(self::TOKEN, config('sync.node_token'));
        $this->assertSame('store', SecretsServiceProvider::$sources['sync_node_token']['source']);
        $this->assertSame('env', SecretsServiceProvider::$sources['health_token']['source']);
        config(['navisuite.secrets.map' => ['sync_node_token' => ['config' => 'sync.node_token', 'env' => 'SYNC_NODE_TOKEN'], 'app_key' => ['config' => 'unset.setting', 'env' => 'X']]]);
        (new SecretsServiceProvider($this->app))->fillIn();
        $this->assertSame('missing', SecretsServiceProvider::$sources['app_key']['source']);
    }

    public function test_a_plaintext_credential_on_a_vessel_is_reported(): void
    {
        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => 7, 'database.connections.vessel.password' => 'plain', 'sync.node_token' => 'plain']);
        (new SecretsServiceProvider($this->app))->fillIn();

        $problems = app(\App\Support\Secrets\SecretsReport::class)->problems();

        $this->assertContains('SYNC_NODE_TOKEN is stored in plaintext in .env. Move it with "php artisan navisuite:secrets import-env".', $problems['warnings']);
        $this->assertContains('VESSEL_DB_PASSWORD is stored in plaintext in .env. Move it with "php artisan navisuite:secrets import-env".', $problems['warnings']);
    }

    public function test_nothing_is_filled_in_while_the_configuration_is_cached_or_shown(): void
    {
        $this->store()->put('sync_node_token', self::TOKEN);

        foreach (['config:cache', 'optimize', 'config:show'] as $command) {
            SecretsServiceProvider::$suppressed = false;
            event(new CommandStarting($command, new ArrayInput([]), new NullOutput));
            $this->assertTrue(SecretsServiceProvider::$suppressed, $command);

            config(['sync.node_token' => '']);
            (new SecretsServiceProvider($this->app))->register();
            $this->assertSame('', config('sync.node_token'), "{$command}: the cached or printed configuration keeps the blank .env value");
        }

        SecretsServiceProvider::$suppressed = false;
        event(new CommandStarting('migrate', new ArrayInput([]), new NullOutput));
        $this->assertFalse(SecretsServiceProvider::$suppressed);
    }

    public function test_the_command_moves_env_values_into_the_store_and_never_prints_them(): void
    {
        $env = $this->dir.'/test.env';
        File::ensureDirectoryExists($this->dir);
        file_put_contents($env, "APP_NAME=NaviSuite\nSYNC_NODE_TOKEN=".self::TOKEN."\nVESSEL_DB_PASSWORD=\"db pass 1\"\nAPP_DEBUG=false\n");

        $this->artisan('navisuite:secrets', ['action' => 'import-env', '--env-file' => $env])
            ->expectsOutputToContain('Moved SYNC_NODE_TOKEN -> sync_node_token')
            ->doesntExpectOutputToContain(self::TOKEN)
            ->assertSuccessful();

        $this->assertSame("APP_NAME=NaviSuite\nSYNC_NODE_TOKEN=\nVESSEL_DB_PASSWORD=\nAPP_DEBUG=false\n", file_get_contents($env));
        $this->app->forgetInstance(SecretStore::class);
        $this->assertSame(self::TOKEN, $this->store()->get('sync_node_token'));
        $this->assertSame('db pass 1', $this->store()->get('db_password'));

        $this->artisan('navisuite:secrets', ['action' => 'status'])->doesntExpectOutputToContain(self::TOKEN)->assertSuccessful();
        $this->artisan('navisuite:secrets', ['action' => 'check'])->expectsOutputToContain('sync_node_token')->assertSuccessful();
    }

    public function test_set_asks_twice_at_a_hidden_prompt(): void
    {
        $this->artisan('navisuite:secrets', ['action' => 'set', 'name' => 'sync_node_token'])
            ->expectsQuestion('Value for sync_node_token (not shown)', self::TOKEN)
            ->expectsQuestion('Once more, to check', self::TOKEN)
            ->expectsOutputToContain('Stored sync_node_token')
            ->assertSuccessful();
        $this->assertSame(self::TOKEN, $this->store()->get('sync_node_token'));

        $this->artisan('navisuite:secrets', ['action' => 'set', 'name' => 'sync_node_token'])
            ->expectsQuestion('Value for sync_node_token (not shown)', 'one')
            ->expectsQuestion('Once more, to check', 'other')
            ->assertFailed();
        $this->artisan('navisuite:secrets', ['action' => 'set', 'name' => 'not_mapped'])->assertFailed();
    }
}
