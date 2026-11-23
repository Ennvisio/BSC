<?php

namespace Tests\Feature\AppMode;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** navisuite:status, navisuite:vessel-offline and the offline_enabled_at migration. */
class AppModeCommandsTest extends TestCase
{
    use BuildsAppModeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppModeFixtures();
    }

    // --------------------------------------------------------------- migration

    public function test_the_migration_adds_a_nullable_column_and_can_be_rolled_back(): void
    {
        $migration = require database_path('migrations/2026_11_20_100000_add_offline_enabled_at_to_vessels_table.php');

        $migration->up();
        $migration->up(); // running it twice is harmless
        $this->assertTrue(Schema::hasColumn('vessels', 'offline_enabled_at'));
        $this->assertNull(DB::table('vessels')->where('id', $this->vesselId)->value('offline_enabled_at'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('vessels', 'offline_enabled_at'));
        $this->assertSame(1, DB::table('vessels')->count(), 'existing rows are untouched');
    }

    // ------------------------------------------------------------ navisuite:status

    public function test_status_reports_cloud_mode(): void
    {
        $this->addOfflineColumn();

        $this->artisan('navisuite:status')
            ->expectsOutputToContain('Vessels running their own onboard server: 0')
            ->expectsOutputToContain('NaviSuite is configured for cloud mode.')
            ->assertSuccessful();
    }

    public function test_status_warns_when_the_migration_is_pending(): void
    {
        $this->artisan('navisuite:status')
            ->expectsOutputToContain('Run "php artisan migrate" to add it.')
            ->assertSuccessful();
    }

    public function test_status_reports_the_vessel_this_server_belongs_to(): void
    {
        $this->useMode('vessel', $this->vesselId);

        $this->artisan('navisuite:status')
            ->expectsOutputToContain("This server belongs to vessel #{$this->vesselId}: MT TEST VESSEL")
            ->expectsOutputToContain('NaviSuite is configured for vessel mode.')
            ->assertSuccessful();
    }

    public function test_status_fails_when_the_vessel_is_not_in_the_database(): void
    {
        $this->useMode('vessel', 999);

        $this->artisan('navisuite:status')
            ->expectsOutputToContain('no vessel with that id exists')
            ->assertFailed();
    }

    public function test_status_fails_when_the_vessel_id_is_missing(): void
    {
        $this->useMode('vessel', null);

        $this->artisan('navisuite:status')
            ->expectsOutputToContain('APP_VESSEL_ID is missing')
            ->assertFailed();
    }

    public function test_status_warns_about_unsafe_vessel_settings(): void
    {
        $this->useMode('vessel', $this->vesselId);
        config(['mail.driver' => 'smtp', 'app.debug' => true]);

        $this->artisan('navisuite:status')
            ->expectsOutputToContain('A vessel server should use the "vessel" connection')
            ->expectsOutputToContain('Use MAIL_DRIVER=log on a vessel server.')
            ->expectsOutputToContain('APP_DEBUG is on.')
            ->assertSuccessful();
    }

    public function test_status_can_skip_the_database(): void
    {
        $this->useMode('vessel', 999);

        $this->artisan('navisuite:status --skip-db')->assertSuccessful();
    }

    // ---------------------------------------------------- navisuite:vessel-offline

    public function test_a_vessel_can_be_marked_and_unmarked(): void
    {
        $this->addOfflineColumn();

        $this->artisan("navisuite:vessel-offline {$this->vesselId} --force")
            ->expectsOutputToContain('is now marked as offline-enabled')
            ->assertSuccessful();
        $this->assertNotNull(DB::table('vessels')->where('id', $this->vesselId)->value('offline_enabled_at'));

        $this->artisan("navisuite:vessel-offline {$this->vesselId} --force")
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();

        $this->artisan("navisuite:vessel-offline {$this->vesselId} --disable --force")
            ->expectsOutputToContain('is no longer marked as offline-enabled')
            ->assertSuccessful();
        $this->assertNull(DB::table('vessels')->where('id', $this->vesselId)->value('offline_enabled_at'));
    }

    public function test_marking_asks_for_confirmation_and_can_be_cancelled(): void
    {
        $this->addOfflineColumn();

        $this->artisan("navisuite:vessel-offline {$this->vesselId}")
            ->expectsConfirmation('Mark MT TEST VESSEL as running its own onboard server? Its ship crew will become read-only on the cloud.', 'no')
            ->expectsOutputToContain('Nothing was changed')
            ->assertFailed();

        $this->assertNull(DB::table('vessels')->where('id', $this->vesselId)->value('offline_enabled_at'));
    }

    public function test_marking_only_touches_the_chosen_vessel(): void
    {
        $this->addOfflineColumn();
        $other = $this->makeVessel('MT OTHER');

        $this->artisan("navisuite:vessel-offline {$this->vesselId} --force")->assertSuccessful();

        $this->assertNull(DB::table('vessels')->where('id', $other->id)->value('offline_enabled_at'));
    }

    public function test_marking_fails_for_an_unknown_vessel(): void
    {
        $this->addOfflineColumn();

        $this->artisan('navisuite:vessel-offline 999 --force')->assertFailed();
        $this->artisan('navisuite:vessel-offline abc --force')->assertFailed();
    }

    public function test_marking_fails_before_the_migration_has_run(): void
    {
        $this->artisan("navisuite:vessel-offline {$this->vesselId} --force")
            ->expectsOutputToContain('Run "php artisan migrate" first.')
            ->assertFailed();
    }

    public function test_marking_is_refused_on_a_vessel_server(): void
    {
        $this->addOfflineColumn();
        $this->useMode('vessel', $this->vesselId);

        $this->artisan("navisuite:vessel-offline {$this->vesselId} --force")
            ->expectsOutputToContain('only runs on the cloud server')
            ->assertFailed();
    }
}
