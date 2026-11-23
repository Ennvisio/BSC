<?php

namespace Tests\Unit;

use App\Support\AppMode;
use Tests\TestCase;

/**
 * The configuration rules behind APP_MODE, APP_VESSEL_ID and
 * APP_SUPPORT_ACCOUNTS (config/navisuite.php), and the database connection
 * each mode selects (config/database.php).
 */
class AppModeTest extends TestCase
{
    private function mode(array $config = []): AppMode
    {
        config(array_merge(['navisuite.mode' => 'cloud', 'navisuite.vessel_id' => null, 'navisuite.support_accounts' => ''], $config));

        return new AppMode;
    }

    public function test_cloud_is_the_default(): void
    {
        foreach ([null, '', '   ', 'cloud', 'CLOUD', ' Cloud '] as $value) {
            $mode = $this->mode(['navisuite.mode' => $value]);

            $this->assertTrue($mode->isCloud(), var_export($value, true).' should mean cloud');
            $this->assertFalse($mode->isVessel());
            $this->assertSame([], $mode->configurationErrors());
        }
    }

    public function test_vessel_mode_is_normalised(): void
    {
        $mode = $this->mode(['navisuite.mode' => ' Vessel ', 'navisuite.vessel_id' => '7']);

        $this->assertTrue($mode->isVessel());
        $this->assertSame('vessel', $mode->mode());
        $this->assertSame(7, $mode->vesselId());
        $this->assertTrue($mode->isConfigured());
    }

    public function test_an_unknown_mode_is_an_error_not_a_silent_fallback(): void
    {
        $mode = $this->mode(['navisuite.mode' => 'vesel']);

        $this->assertFalse($mode->isCloud());
        $this->assertFalse($mode->isVessel());
        $this->assertFalse($mode->isConfigured());
        $this->assertStringContainsString('"vesel"', $mode->configurationErrors()[0]);
    }

    public function test_vessel_mode_requires_a_valid_vessel_id(): void
    {
        foreach ([null, '', 'abc', '0', '-3', '2.5'] as $value) {
            $mode = $this->mode(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => $value]);

            $this->assertNull($mode->vesselId(), var_export($value, true).' is not a vessel id');
            $this->assertCount(1, $mode->configurationErrors(), var_export($value, true).' must be rejected');
        }
    }

    public function test_cloud_mode_ignores_the_vessel_id(): void
    {
        $this->assertSame([], $this->mode(['navisuite.vessel_id' => 'abc'])->configurationErrors());
    }

    public function test_support_accounts_are_parsed_case_insensitively(): void
    {
        $mode = $this->mode(['navisuite.support_accounts' => ' Support@Office.example , ,it@office.example ']);

        $this->assertSame(['support@office.example', 'it@office.example'], $mode->supportAccounts());
    }

    public function test_the_vessel_database_connection_is_local_by_default(): void
    {
        $vessel = $this->databaseConfig(['DB_CONNECTION' => null])['connections']['vessel'];

        $this->assertSame('mysql', $vessel['driver']);
        $this->assertSame('127.0.0.1', $vessel['host']);
        $this->assertSame('navisuite', $vessel['database']);
        $this->assertSame('utf8mb4', $vessel['charset']);
        $this->assertTrue($vessel['strict']);
    }

    public function test_the_default_connection_follows_the_mode_only_when_db_connection_is_unset(): void
    {
        // Existing cloud servers: no APP_MODE, or cloud - unchanged.
        $this->assertSame('mysql', $this->databaseConfig(['DB_CONNECTION' => null, 'APP_MODE' => null])['default']);
        $this->assertSame('mysql', $this->databaseConfig(['DB_CONNECTION' => null, 'APP_MODE' => 'cloud'])['default']);

        // A vessel server with no explicit connection gets the local one.
        $this->assertSame('vessel', $this->databaseConfig(['DB_CONNECTION' => null, 'APP_MODE' => 'Vessel'])['default']);

        // An explicit DB_CONNECTION always wins.
        $this->assertSame('mysql', $this->databaseConfig(['DB_CONNECTION' => 'mysql', 'APP_MODE' => 'vessel'])['default']);
    }

    /**
     * Evaluates config/database.php with the given environment variables
     * (null = unset), then restores the environment exactly as it was.
     */
    private function databaseConfig(array $env): array
    {
        $saved = [];
        foreach ($env as $key => $value) {
            $saved[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            $this->setEnv($key, $value === null ? false : $value, $value, $value);
        }

        try {
            return require config_path('database.php');
        } finally {
            foreach ($saved as $key => [$getenv, $envVar, $serverVar]) {
                $this->setEnv($key, $getenv, $envVar, $serverVar);
            }
        }
    }

    private function setEnv(string $key, string|false $getenv, ?string $envVar, ?string $serverVar): void
    {
        $getenv === false ? putenv($key) : putenv("{$key}={$getenv}");

        if ($envVar === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $envVar;
        }

        if ($serverVar === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $serverVar;
        }
    }
}
