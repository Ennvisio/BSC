<?php

namespace Tests\Feature\VesselServer;

use App\Support\Heartbeat;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\Concerns\BuildsSyncSchema;
use Tests\TestCase;

/** GET /health on the cloud and on a vessel server. */
class HealthCheckTest extends TestCase
{
    use BuildsRequisitionSchema;
    use BuildsSyncSchema;

    private const LAN_CLIENT = '192.168.10.50';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
    }

    private function vesselMode(): void
    {
        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => $this->vesselId]);
    }

    private function allHeartbeats(): void
    {
        Heartbeat::record(Heartbeat::SCHEDULER);
        Heartbeat::record(Heartbeat::QUEUE);
        Heartbeat::record(Heartbeat::DISCOVERY);
    }

    private function fromLan()
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::LAN_CLIENT]);
    }

    // ------------------------------------------------------------------ cloud

    public function test_the_cloud_does_not_expose_it_by_default(): void
    {
        $this->getJson('/health')->assertNotFound();
    }

    public function test_the_cloud_can_switch_it_on(): void
    {
        config(['navisuite.health.enabled' => true]);

        $this->getJson('/health')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'app' => 'NaviSuite', 'mode' => 'cloud', 'vessel' => null])
            ->assertJsonMissingPath('checks.scheduler');
    }

    // ----------------------------------------------------------------- vessel

    public function test_a_healthy_vessel_server_reports_ok_and_which_vessel_it_is(): void
    {
        $this->vesselMode();
        $this->allHeartbeats();
        config(['queue.default' => 'database']);

        $this->getJson('/health')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson([
                'status' => 'ok',
                'mode' => 'vessel',
                'vessel' => ['id' => $this->vesselId, 'name' => 'MT TEST VESSEL'],
                'checks' => [
                    'configuration' => ['ok' => true], 'database' => ['ok' => true], 'storage' => ['ok' => true],
                    'php' => ['ok' => true], 'disk' => ['ok' => true], 'scheduler' => ['ok' => true], 'queue' => ['ok' => true],
                ],
            ]);
    }

    public function test_it_tells_desktop_clients_which_versions_it_accepts_even_from_the_lan(): void
    {
        $this->vesselMode();
        config(['navisuite.compat.min_client_version' => '1.2.0']);

        $this->fromLan()->getJson('/health')
            ->assertOk()
            ->assertJsonPath('clients', ['api' => 1, 'min_version' => '1.2.0'])
            ->assertJsonMissingPath('checks');
    }

    public function test_it_sets_no_session_cookie(): void
    {
        $this->vesselMode();

        $response = $this->get('/health');

        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_stopped_background_services_make_it_degraded_not_down(): void
    {
        $this->vesselMode();
        config(['queue.default' => 'database']);

        $this->getJson('/health')
            ->assertOk()
            ->assertJson(['status' => 'degraded', 'checks' => [
                'scheduler' => ['ok' => false, 'critical' => false],
                'queue' => ['ok' => false, 'critical' => false],
                'discovery' => ['ok' => false, 'critical' => false],
            ]])
            ->assertJsonPath('checks.scheduler.message', 'No heartbeat yet. Is the NaviSuite-Scheduler service running?');
    }

    public function test_an_old_heartbeat_counts_as_stopped(): void
    {
        $this->vesselMode();
        $this->allHeartbeats();
        config(['navisuite.health.scheduler_max_age' => -1]);

        $this->getJson('/health')->assertJsonPath('checks.scheduler.ok', false)->assertJsonPath('status', 'degraded');
    }

    public function test_a_vessel_missing_from_the_database_is_down(): void
    {
        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => 999]);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJson(['status' => 'down', 'vessel' => null, 'checks' => ['database' => ['ok' => false, 'critical' => true]]]);
    }

    public function test_a_misconfigured_server_still_answers_and_says_why(): void
    {
        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => null]);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('checks.configuration.ok', false);
    }

    public function test_a_missing_php_extension_is_down(): void
    {
        $this->vesselMode();
        config(['navisuite.health.required_extensions' => ['pdo_mysql', 'not_a_real_extension']]);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('checks.php.message', 'Missing extensions: not_a_real_extension.');
    }

    public function test_low_disk_space_is_degraded(): void
    {
        $this->vesselMode();
        $this->allHeartbeats();
        config(['navisuite.health.min_free_disk_mb' => PHP_INT_MAX]);

        $this->getJson('/health')->assertOk()->assertJsonPath('status', 'degraded')->assertJsonPath('checks.disk.ok', false);
    }

    public function test_it_reports_whether_changes_are_being_recorded_for_sync(): void
    {
        $this->vesselMode();
        $this->allHeartbeats();
        $this->runSyncMigrations();
        // The partial test schema has only some of the 56 tables.
        config(['sync.tables' => array_filter(config('sync.tables'), fn ($t) => Schema::hasTable($t) && $t !== 'pms_used_parts', ARRAY_FILTER_USE_KEY)]);

        $this->getJson('/health')->assertJsonPath('checks.sync_capture.ok', false)->assertJsonPath('status', 'degraded');

        $this->installSyncTriggers();

        $this->getJson('/health')->assertJsonPath('checks.sync_capture.ok', true)->assertJsonPath('status', 'ok');
    }

    // ---------------------------------------------------------------- privacy

    public function test_lan_clients_get_the_summary_without_the_details(): void
    {
        $this->vesselMode();

        $this->fromLan()->getJson('/health')
            ->assertOk()
            ->assertJsonStructure(['status', 'app', 'mode', 'vessel', 'release', 'time'])
            ->assertJsonMissingPath('checks');
    }

    public function test_listed_addresses_and_the_token_unlock_the_details(): void
    {
        $this->vesselMode();
        config(['navisuite.health.detail_ips' => '10.0.0.0/8, 192.168.10.0/24', 'navisuite.health.token' => 'secret-token']);

        $this->fromLan()->getJson('/health')->assertJsonStructure(['checks']);
        $this->withServerVariables(['REMOTE_ADDR' => '172.16.0.9'])->getJson('/health')->assertJsonMissingPath('checks');
        $this->withServerVariables(['REMOTE_ADDR' => '172.16.0.9'])->getJson('/health', ['X-Health-Token' => 'wrong'])->assertJsonMissingPath('checks');
        $this->withServerVariables(['REMOTE_ADDR' => '172.16.0.9'])->getJson('/health', ['X-Health-Token' => 'secret-token'])->assertJsonStructure(['checks']);
    }
}
