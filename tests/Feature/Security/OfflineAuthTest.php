<?php

namespace Tests\Feature\Security;

use App\Sync\Models\SyncConflict;
use App\Sync\SensitiveValues;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Sync\Modules\BusinessSyncTestCase;

/**
 * Signing in on board without the cloud (Checkpoint 7): against the bcrypt
 * hashes that came down with the users table, with the office's
 * deactivations and transfers taking effect on board after the next sync.
 */
class OfflineAuthTest extends BusinessSyncTestCase
{
    private function login(string $email, string $password)
    {
        $this->app['auth']->forgetGuards();

        return $this->post('/login', ['email' => $email, 'password' => $password]);
    }

    private function logout(): void
    {
        $this->app['auth']->guard()->logout();
        $this->app['auth']->forgetGuards();
    }

    public function test_crew_sign_in_on_board_with_the_password_the_office_gave_them_and_no_cloud(): void
    {
        $this->network = 'down';
        $this->onShip(function () {
            $this->login('ce@mvtest.test', 'secret')->assertRedirect('/home');
            $this->assertAuthenticatedAs($this->user('ce@mvtest.test'));
            $this->logout();

            $this->login('ce@mvtest.test', 'wrong')->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->login('gm.srd@office.test', 'secret')->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->login('master@mvother.test', 'secret')->assertSessionHasErrors('email');
            $this->assertGuest();

            $hash = DB::table('users')->where('email', 'ce@mvtest.test')->value('password');
            $this->assertTrue(str_starts_with($hash, '$2y$'), 'only a bcrypt hash is stored');
        });
    }

    public function test_repeated_wrong_passwords_lock_the_account_for_a_while(): void
    {
        $this->onShip(function () {
            for ($i = 0; $i < 5; $i++) {
                $this->login('ce@mvtest.test', 'guess'.$i);
            }
            $this->login('ce@mvtest.test', 'secret')->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertStringContainsString('Too many', session('errors')->first('email'));
        });
    }

    public function test_a_user_deactivated_in_the_office_is_signed_out_on_board_and_cannot_sign_in_again(): void
    {
        $this->onShip(fn () => $this->login('2e@mvtest.test', 'secret')->assertRedirect('/home'));
        $this->onCloud(fn () => $this->as('admin@office.test')->post('/user/delete', ['id' => $this->userId('2e@mvtest.test')]));
        $this->assertFalse((bool) $this->onCloud(fn () => DB::table('users')->where('email', '2e@mvtest.test')->value('status')));

        $this->assertCycleOk();

        $this->onShip(function () {
            $this->assertFalse((bool) DB::table('users')->where('email', '2e@mvtest.test')->value('status'), 'the flag came down');
            $this->actingAs($this->user('2e@mvtest.test'));
            $this->get('/home')->assertRedirect(route('login'));
            $this->assertGuest();

            $this->login('2e@mvtest.test', 'secret')->assertSessionHasErrors(['email' => 'This account has been deactivated.']);
            $this->assertGuest();
        });
        $this->onCloud(function () {
            $this->login('2e@mvtest.test', 'secret')->assertSessionHasErrors('email');
            $this->assertGuest();
        });
    }

    public function test_a_crew_member_moved_to_another_vessel_can_no_longer_sign_in_on_the_old_one(): void
    {
        $this->onCloud(function () {
            $role = \App\Role::query()->where('user_id', $this->userId('2e@mvtest.test'))->firstOrFail();
            $role->vessel_id = 8;
            $role->save();
        });

        $this->assertCycleOk();

        $this->onShip(function () {
            $this->assertSame(8, (int) DB::table('roles')->where('user_id', $this->userId('2e@mvtest.test'))->value('vessel_id'), 'the move reached the vessel left behind');
            $this->assertFalse(Hash::check('secret', DB::table('users')->where('email', '2e@mvtest.test')->value('password')), 'and the password no longer works there');
            $this->login('2e@mvtest.test', 'secret')->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->login('ce@mvtest.test', 'secret')->assertRedirect('/home');
        });
        $this->assertSame([], $this->inboxProblems('ship'));
    }

    public function test_a_role_change_in_the_office_applies_on_board(): void
    {
        $this->onCloud(fn () => DB::table('roles')->where('user_id', $this->userId('2e@mvtest.test'))->update(['role' => 'chief-engineer', 'updated_at' => now()->addMinute()]));

        $this->assertCycleOk();

        $this->onShip(fn () => $this->assertSame('chief-engineer', $this->user('2e@mvtest.test')->role->role));
    }

    public function test_password_hashes_are_not_kept_in_sync_bookkeeping(): void
    {
        $this->onShip(fn () => DB::table('users')->where('email', 'master@mvtest.test')->update(['password' => Hash::make('ship-pass'), 'updated_at' => '2026-11-21 09:00:00']));
        $this->onCloud(fn () => DB::table('users')->where('email', 'master@mvtest.test')->update(['password' => Hash::make('office-reset'), 'updated_at' => '2026-11-21 10:00:00']));

        $this->assertCycleOk();
        $this->assertCycleOk();

        foreach (['cloud', 'ship'] as $server) {
            $this->on($server, function () use ($server) {
                foreach (SyncConflict::query()->where('table_name', 'users')->get() as $conflict) {
                    $this->assertSame(SensitiveValues::MASK, $conflict->local_values['password'] ?? SensitiveValues::MASK, $server);
                    $this->assertSame(SensitiveValues::MASK, $conflict->remote_values['password'] ?? SensitiveValues::MASK, $server);
                }
                foreach (DB::table('sync_inbox')->where('table_name', 'users')->whereIn('status', ['applied', 'skipped'])->pluck('payload') as $payload) {
                    $this->assertStringNotContainsString('$2y$', (string) $payload, "{$server}: applied inbox entries keep no hash");
                }
                $this->assertStringNotContainsString('$2y$', (string) DB::table('sync_logs')->pluck('context')->implode(' '));
            });
        }
        $this->assertSame(1, $this->onCloud(fn () => SyncConflict::query()->where('table_name', 'users')->count()));
        $this->assertTrue($this->onShip(fn () => Hash::check('office-reset', DB::table('users')->where('email', 'master@mvtest.test')->value('password'))), 'the newest password still wins');
    }
}
