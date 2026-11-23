<?php

namespace Tests\Feature\AppMode;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * APP_MODE=cloud (the default): behaviour is unchanged, except that ship crew
 * of a vessel marked with vessels.offline_enabled_at can view but not change
 * data on the cloud.
 */
class CloudModeTest extends TestCase
{
    use BuildsAppModeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppModeFixtures();
    }

    private function markOffline(int $vesselId): void
    {
        DB::table('vessels')->where('id', $vesselId)->update(['offline_enabled_at' => now()]);
    }

    // ------------------------------------------------------- unchanged behaviour

    public function test_cloud_is_the_mode_when_nothing_is_configured(): void
    {
        $this->assertTrue(app(\App\Support\AppMode::class)->isCloud());
    }

    public function test_shore_and_ship_users_of_any_vessel_can_sign_in(): void
    {
        $users = [
            $this->makeUser('gm-srd'),
            $this->makeUser('super-admin'),
            $this->makeUser('master', $this->vesselId),
            $this->makeUser('chief-officer', $this->makeVessel('MT OTHER')->id),
        ];

        foreach ($users as $user) {
            $this->post('/login', ['email' => $user->email, 'password' => 'x'])->assertRedirect('/home');
            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        }
    }

    public function test_registration_and_password_reset_pages_are_unchanged(): void
    {
        $this->get('/register')->assertOk();
        $this->get('/password/reset')->assertOk();
    }

    public function test_writes_work_before_the_migration_has_run(): void
    {
        // Production may deploy the code before running `php artisan migrate`.
        $master = $this->makeUser('master', $this->vesselId);

        $this->actingAs($master)->post('/__mode-probe')->assertOk()->assertSee('write ok');
    }

    public function test_writes_work_when_no_vessel_is_marked_offline(): void
    {
        $this->addOfflineColumn();

        $this->actingAs($this->makeUser('master', $this->vesselId))->post('/__mode-probe')->assertOk();
        $this->actingAs($this->makeUser('gm-srd'))->post('/__mode-probe')->assertOk();
    }

    public function test_vessel_mode_restrictions_do_not_apply_on_the_cloud(): void
    {
        // A path that is blocked on board must reach its controller on the
        // cloud. The probe stands in for it, so no controller is involved.
        config(['navisuite.vessel.blocked_write_paths' => ['__mode-probe']]);

        $this->actingAs($this->makeUser('super-admin'))->post('/__mode-probe')->assertOk();
    }

    // ---------------------------------------------------- offline-enabled vessels

    public function test_crew_of_an_offline_vessel_are_read_only_on_the_cloud(): void
    {
        $this->addOfflineColumn();
        $this->markOffline($this->vesselId);
        $master = $this->makeUser('master', $this->vesselId);

        $this->actingAs($master)->get('/__mode-probe')->assertOk();
        $this->actingAs($master)->postJson('/__mode-probe')
            ->assertStatus(403)
            ->assertJsonFragment(['message' => 'Your vessel now uses its own onboard NaviSuite server. Please make changes there. The office copy is read-only for your vessel.']);
        $this->actingAs($master)->post('/__mode-probe')->assertStatus(403);
    }

    public function test_crew_of_an_offline_vessel_can_still_sign_in_search_and_sign_out(): void
    {
        $this->addOfflineColumn();
        $this->markOffline($this->vesselId);
        $master = $this->makeUser('master', $this->vesselId);

        $this->post('/login', ['email' => $master->email, 'password' => 'x'])->assertRedirect('/home');
        $this->assertAuthenticatedAs($master);

        config(['navisuite.read_only_write_paths' => array_merge(config('navisuite.read_only_write_paths'), ['__mode-probe'])]);
        $this->post('/__mode-probe')->assertOk();

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_other_vessels_and_shore_users_are_not_affected(): void
    {
        $this->addOfflineColumn();
        $this->markOffline($this->vesselId);

        $otherCrew = $this->makeUser('master', $this->makeVessel('MT OTHER')->id);
        $this->actingAs($otherCrew)->post('/__mode-probe')->assertOk();

        foreach (['gm-srd', 'am-ssm', 'super-admin'] as $role) {
            $this->actingAs($this->makeUser($role))->post('/__mode-probe')->assertOk();
        }
    }

    public function test_the_lock_can_be_switched_off(): void
    {
        $this->addOfflineColumn();
        $this->markOffline($this->vesselId);
        config(['navisuite.cloud.lock_offline_vessels' => false]);

        $this->actingAs($this->makeUser('master', $this->vesselId))->post('/__mode-probe')->assertOk();
    }
}
