<?php

namespace Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;

/**
 * Checkpoint 8. The installers are built on macOS or Linux and run on
 * Windows, where no test runs. These checks catch, before a build, the
 * mistakes that would only show on a ship's PC: versions that disagree,
 * PowerShell files Windows PowerShell 5.1 misreads, answer-file keys the
 * setup script ignores, and a credential reaching a log.
 */
class InstallerConsistencyTest extends TestCase
{
    private function root(string $path = ''): string
    {
        return dirname(__DIR__, 3).($path === '' ? '' : '/'.$path);
    }

    private function json(string $path): array
    {
        return json_decode(file_get_contents($this->root($path)), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    private function powershellFiles(): array
    {
        return array_merge(glob($this->root('deploy/windows/*.ps1')), glob($this->root('deploy/windows/lib/*.ps1')));
    }

    public function test_both_installers_carry_one_version(): void
    {
        $product = $this->json('deploy/product.json');

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $product['version']);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $product['min_upgrade_from']);
        $this->assertSame($product['version'], $this->json('desktop/package.json')['version'], 'desktop/package.json and deploy/product.json');
        $this->assertTrue(version_compare($product['min_upgrade_from'], $product['version'], '<='));
    }

    public function test_the_server_never_requires_a_client_newer_than_the_one_it_ships(): void
    {
        $config = require $this->root('config/navisuite.php');
        $product = $this->json('deploy/product.json');

        $this->assertTrue(version_compare($config['compat']['min_client_version'], $product['version'], '<='));
        $this->assertSame(1, $config['compat']['client_api']);
        $this->assertStringContainsString('SERVER_API = Object.freeze({ min: 1, max: 1 })', file_get_contents($this->root('desktop/src/main/compat.js')),
            'the client understands the client_api the server publishes');
    }

    public function test_release_json_finds_the_values_it_reads_from_the_source(): void
    {
        // deploy/build-release.sh reads these with regular expressions.
        $this->assertMatchesRegularExpression('/.client_api. => (\d+)/', file_get_contents($this->root('config/navisuite.php')));
        $this->assertMatchesRegularExpression('/APP_MIN_CLIENT_VERSION., .([0-9.]+)./', file_get_contents($this->root('config/navisuite.php')));
        $this->assertMatchesRegularExpression('/const PROTOCOL = (\d+);/', file_get_contents($this->root('app/Sync/Engine/SyncServer.php')));
    }

    public function test_powershell_files_are_plain_ascii(): void
    {
        // Windows PowerShell 5.1 reads a file without a byte-order mark as
        // the PC's ANSI code page, so anything beyond ASCII may break a script.
        foreach (array_merge($this->powershellFiles(), glob($this->root('deploy/windows/templates/*'))) as $file) {
            $this->assertSame(1, preg_match('/^[\x09\x0A\x0D\x20-\x7E]*$/', file_get_contents($file)), basename($file).' has characters outside ASCII');
        }
    }

    public function test_every_answer_file_key_is_read_by_the_setup_script(): void
    {
        $setup = file_get_contents($this->root('deploy/windows/setup-server.ps1'));
        $ini = parse_ini_file($this->root('deploy/installer/server/answers.example.ini'), true, INI_SCANNER_RAW);

        $this->assertNotEmpty($ini['server'] ?? null);
        foreach ($ini as $section => $keys) {
            foreach (array_keys($keys) as $key) {
                $this->assertStringContainsString("'".strtolower("$section.$key")."'", $setup, "answers.example.ini [$section] $key is not used");
            }
        }
        // The guided installer writes these.
        $nsi = file_get_contents($this->root('deploy/installer/server/NaviSuite-Server.nsi'));
        preg_match_all('/WriteINIStr "\$AnswerFile" "(\w+)" "(\w+)"/', $nsi, $m, PREG_SET_ORDER);
        $this->assertNotEmpty($m);
        foreach ($m as [, $section, $key]) {
            $this->assertStringContainsString("'".strtolower("$section.$key")."'", $setup, "the installer writes [$section] $key, which is not used");
        }
    }

    public function test_the_sync_token_never_reaches_a_log_a_file_or_a_command_line(): void
    {
        $setup = file_get_contents($this->root('deploy/windows/setup-server.ps1'));
        foreach (explode("\n", $setup) as $n => $line) {
            if (preg_match('/Write-(Ns\w+|Host|Output|Verbose)|\$result\.|Write-NsFile|Out-File|Set-Content/', $line)) {
                $this->assertDoesNotMatchRegularExpression('/\$token\b|NAVISUITE_SETUP_SYNC_TOKEN|SyncNodeToken/', $line, 'setup-server.ps1 line '.($n + 1));
            }
        }
        $this->assertStringContainsString("Remove-Item Env:\\NAVISUITE_SETUP_SYNC_TOKEN", $setup, 'the environment copy is removed once read');

        $nsi = file_get_contents($this->root('deploy/installer/server/NaviSuite-Server.nsi'));
        $this->assertStringNotContainsString('WriteINIStr "$AnswerFile" "sync" "Token"', $nsi, 'the guided installer never writes the token to a file');
        foreach (explode("\n", $nsi) as $n => $line) {
            if (preg_match('/!insertmacro Log|DetailPrint|StrCpy \$0 \'"\$WINDIR/', $line)) {
                $this->assertStringNotContainsString('$SyncToken', $line, 'NaviSuite-Server.nsi line '.($n + 1));
            }
        }
        $this->assertStringContainsString('SetEnvironmentVariable(t "NAVISUITE_SETUP_SYNC_TOKEN", p 0)', $nsi, 'cleared after setup-server.ps1 ran');
    }

    public function test_the_installer_is_built_with_the_defines_it_requires(): void
    {
        $nsi = file_get_contents($this->root('deploy/installer/server/NaviSuite-Server.nsi'));
        $build = file_get_contents($this->root('deploy/build-installers.sh'));

        preg_match('/!ifndef (NS_[A-Z_]+(?: \| NS_[A-Z_]+)*)/', $nsi, $m);
        foreach (explode(' | ', $m[1]) as $define) {
            $this->assertStringContainsString("-D$define=", $build, "build-installers.sh does not pass $define");
        }
    }

    public function test_test_builds_cannot_be_installed_by_accident(): void
    {
        $nsi = file_get_contents($this->root('deploy/installer/server/NaviSuite-Server.nsi'));
        $setup = file_get_contents($this->root('deploy/windows/setup-server.ps1'));
        $build = file_get_contents($this->root('deploy/build-installers.sh'));

        $this->assertStringContainsString('is installed silently only with /TESTBUILD', $nsi);
        $this->assertStringContainsString('if ($new.test_build -and -not $AcceptTestBuild)', $setup);
        $this->assertStringContainsString('NAVISUITE_TEST_BUILD=$TEST_BUILD deploy/build-release.sh', $build);
        // The production gate: clean tree, no failed or skipped tests, client tests and smoke test.
        foreach (['uncommitted changes', '"failures"] + $r["errors"] + $r["skipped"]', 'npm test', 'npm run smoke', 'SYNC_MYSQL_TEST_DATABASE'] as $needle) {
            $this->assertStringContainsString($needle, $build);
        }
    }

    public function test_every_script_the_installer_and_tasks_call_exists(): void
    {
        $setup = file_get_contents($this->root('deploy/windows/setup-server.ps1'));
        $lib = file_get_contents($this->root('deploy/windows/lib/NaviSuite.ps1'));
        preg_match_all("/'([a-z-]+\.ps1)'/", $setup.$lib, $m);
        $this->assertNotEmpty($m[1]);
        foreach (array_unique($m[1]) as $script) {
            $this->assertFileExists($this->root("deploy/windows/$script"));
        }
        $this->assertStringContainsString("deploy\\windows\\startup-check.ps1", $lib);
        $this->assertStringContainsString("Unregister-ScheduledTask -TaskName 'NaviSuite Startup Check'", file_get_contents($this->root('deploy/windows/uninstall-server.ps1')));
    }
}
