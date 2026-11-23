<?php

namespace Tests\Feature\AppMode;

use App\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * APP_MODE=vessel: only this vessel's crew (and listed support accounts)
 * may use the server, and office-maintained data is read-only on board.
 * See App\Http\Middleware\EnforceAppMode and App\Providers\AppModeServiceProvider.
 */
class VesselModeTest extends TestCase
{
    use BuildsAppModeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppModeFixtures();
        $this->useMode('vessel', $this->vesselId, 'Support@Office.example');
    }

    // ------------------------------------------------------------- sign-in

    public function test_crew_of_this_vessel_can_sign_in(): void
    {
        $master = $this->makeUser('master', $this->vesselId);

        $this->post('/login', ['email' => $master->email, 'password' => 'x'])
            ->assertRedirect('/home');

        $this->assertAuthenticatedAs($master);
    }

    public function test_crew_of_another_vessel_cannot_sign_in(): void
    {
        $other = $this->makeUser('master', $this->makeVessel('MT OTHER')->id);

        $this->post('/login', ['email' => $other->email, 'password' => 'x'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_shore_users_cannot_sign_in(): void
    {
        foreach (['gm-srd', 'am-ssm', 'super-admin'] as $role) {
            $user = $this->makeUser($role);

            $this->post('/login', ['email' => $user->email, 'password' => 'x'])
                ->assertSessionHasErrors('email');

            $this->assertGuest();
        }
    }

    public function test_a_shore_role_wrongly_linked_to_this_vessel_still_cannot_sign_in(): void
    {
        $user = $this->makeUser('gm-srd', $this->vesselId);

        $this->post('/login', ['email' => $user->email, 'password' => 'x'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_user_without_a_role_cannot_sign_in(): void
    {
        $user = User::create(['name' => 'nobody', 'email' => 'nobody@test.local', 'password' => 'x']);

        $this->post('/login', ['email' => $user->email, 'password' => 'x'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_wrong_password_is_still_rejected_for_crew(): void
    {
        $master = $this->makeUser('master', $this->vesselId);

        $this->post('/login', ['email' => $master->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_listed_support_account_can_sign_in(): void
    {
        $support = $this->makeUser('super-admin', null, null, 'support@office.example');

        $this->post('/login', ['email' => $support->email, 'password' => 'x'])
            ->assertRedirect('/home');

        $this->assertAuthenticatedAs($support);
    }

    public function test_an_existing_session_of_a_user_who_does_not_belong_here_is_ended(): void
    {
        $shore = $this->makeUser('gm-srd');

        $this->actingAs($shore)->get('/__mode-probe')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_ajax_request_from_a_user_who_does_not_belong_here_gets_401(): void
    {
        $this->actingAs($this->makeUser('gm-srd'))->getJson('/__mode-probe')
            ->assertStatus(401);

        $this->assertGuest();
    }

    // ------------------------------------------------------- what crew can do

    public function test_crew_can_view_and_change_their_own_data(): void
    {
        $chiefOfficer = $this->makeUser('chief-officer', $this->vesselId);

        $this->actingAs($chiefOfficer)->get('/__mode-probe')->assertOk()->assertSee('read ok');
        $this->actingAs($chiefOfficer)->post('/__mode-probe')->assertOk()->assertSee('write ok');
    }

    public static function officeMaintainedWrites(): array
    {
        return [
            'create user' => ['/user/store'],
            'update user' => ['/user/update'],
            'delete user' => ['/user/delete'],
            'vessel particulars' => ['/vessel/store/engine'],
            'vessel general info' => ['/vessel/store/gen-info'],
            'delete vessel' => ['/vessel/delete'],
            'vessel status' => ['/vessel/toggle-status'],
            'create category' => ['/category/store'],
            'delete category' => ['/category/delete'],
            'PMS master data' => ['/pms/master/locations'],
            'PMS master data update' => ['/pms/master/job-types/3/update'],
            'trash restore' => ['/restore'],
            'trash delete' => ['/permanent-delete'],
            'procurement' => ['/procurement/5/complete'],
            'service procurement' => ['/service-procurement/5/complete'],
        ];
    }

    #[DataProvider('officeMaintainedWrites')]
    public function test_office_maintained_data_cannot_be_changed_on_board(string $path): void
    {
        $master = $this->makeUser('master', $this->vesselId);

        $this->actingAs($master)->postJson($path)
            ->assertStatus(403)
            ->assertJsonFragment(['message' => 'This information is maintained by the office and cannot be changed on board. Changes made in the office will reach the vessel by synchronisation.']);
    }

    public function test_a_blocked_form_post_gets_a_403_page(): void
    {
        $this->actingAs($this->makeUser('master', $this->vesselId))->post('/category/store')
            ->assertStatus(403)
            ->assertSee('maintained by the office');
    }

    public function test_registration_and_password_reset_are_not_available_on_board(): void
    {
        $this->get('/register')->assertStatus(403);
        $this->post('/register')->assertStatus(403);
        $this->get('/password/reset')->assertStatus(403);
        $this->post('/password/email', ['email' => 'a@b.c'])->assertStatus(403);
    }

    // ---------------------------------------------------------- support account

    public function test_a_support_account_is_read_only(): void
    {
        $support = $this->makeUser('super-admin', null, null, 'support@office.example');

        $this->actingAs($support)->get('/__mode-probe')->assertOk();
        $this->actingAs($support)->postJson('/__mode-probe')
            ->assertStatus(403)
            ->assertJsonFragment(['message' => 'Support accounts are read-only on the vessel server.']);
    }

    public function test_a_support_account_can_still_sign_out(): void
    {
        $support = $this->makeUser('super-admin', null, null, 'support@office.example');

        $this->actingAs($support)->post('/logout')->assertRedirect();

        $this->assertGuest();
    }

    // ----------------------------------------------------------- misconfiguration

    public function test_a_vessel_server_without_a_vessel_id_refuses_to_serve(): void
    {
        $this->useMode('vessel', null);

        $this->get('/login')->assertStatus(503);
    }

    public function test_an_unknown_mode_refuses_to_serve(): void
    {
        $this->useMode('vesel', $this->vesselId);

        $this->get('/login')->assertStatus(503);
    }
}
