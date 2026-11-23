<?php

namespace Tests\Feature\Sync\Modules;

use App\Sync\Models\SyncConflict;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Master data is the office's: users, roles, the vessel record and the PMS
 * reference lists come down. The crew may change their own password on
 * board (Q4); shore users arrive without their password (roadmap 5.7).
 */
class MasterDataSyncTest extends BusinessSyncTestCase
{
    private function newUser(string $email, string $role, ?int $vesselId, string $password): int
    {
        return $this->onCloud(function () use ($email, $role, $vesselId, $password) {
            $id = DB::table('users')->insertGetId(['name' => $role, 'email' => $email, 'password' => Hash::make($password), 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('roles')->insert(['user_id' => $id, 'vessel_id' => $vesselId, 'role' => $role, 'user_type' => \App\Role::TYPE_BY_ROLE[$role], 'created_by' => 'admin', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        });
    }

    public function test_new_crew_come_down_able_to_sign_in_and_shore_users_without_their_password(): void
    {
        $this->newUser('3e@mvtest.test', 'second-engineer', 7, 'crew-secret');
        $this->newUser('am.srd@office.test', 'am-srd', null, 'office-secret');

        $this->assertCycleOk();

        $this->onShip(function () {
            $crew = DB::table('users')->where('email', '3e@mvtest.test')->first();
            $this->assertTrue(Hash::check('crew-secret', $crew->password), 'the crew member can sign in on board');
            $this->assertSame([7, 'second-engineer'], [(int) DB::table('roles')->where('user_id', $crew->id)->value('vessel_id'), DB::table('roles')->where('user_id', $crew->id)->value('role')]);

            $shore = DB::table('users')->where('email', 'am.srd@office.test')->first();
            $this->assertNotNull($shore, 'shore users are known on board (names on signatures)');
            $this->assertFalse(Hash::check('office-secret', $shore->password), 'but cannot sign in there');
            $this->assertSame('am-srd', DB::table('roles')->where('user_id', $shore->id)->value('role'));
        });
        $this->assertTrue($this->onCloud(fn () => Hash::check('office-secret', DB::table('users')->where('email', 'am.srd@office.test')->value('password'))), 'untouched in the office');
    }

    public function test_a_password_changed_on_board_reaches_the_office(): void
    {
        $this->onShip(fn () => $this->as('master@mvtest.test')->post('/change/password', [
            'current_password' => 'secret', 'new_password' => 'new-secret', 'new_password_confirmation' => 'new-secret',
        ])->assertRedirect());

        $this->assertCycleOk();

        $this->assertTrue($this->onCloud(fn () => Hash::check('new-secret', DB::table('users')->where('email', 'master@mvtest.test')->value('password'))));
        $this->assertSame('Master', $this->onCloud(fn () => DB::table('users')->where('email', 'master@mvtest.test')->value('name')), 'nothing else taken from the vessel');
    }

    public function test_when_both_change_a_password_the_newest_wins_on_both_sides(): void
    {
        $this->onShip(fn () => DB::table('users')->where('email', 'master@mvtest.test')->update(['password' => Hash::make('ship-pass'), 'updated_at' => '2026-11-21 09:00:00']));
        $this->onCloud(fn () => DB::table('users')->where('email', 'master@mvtest.test')->update(['password' => Hash::make('office-reset'), 'updated_at' => '2026-11-21 10:00:00']));

        $this->assertCycleOk();
        $this->assertCycleOk();

        foreach (['cloud', 'ship'] as $server) {
            $this->assertTrue($this->on($server, fn () => Hash::check('office-reset', DB::table('users')->where('email', 'master@mvtest.test')->value('password'))), $server);
        }
        $this->assertSame(1, $this->onCloud(fn () => SyncConflict::query()->where('table_name', 'users')->count()));
    }

    public function test_the_vessel_cannot_create_users_or_change_office_records(): void
    {
        $this->onShip(function () {
            DB::table('users')->insert(['name' => 'Stowaway', 'email' => 'x@mvtest.test', 'password' => 'x', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('vessels')->where('id', 7)->update(['name' => 'RENAMED ON BOARD']);
            DB::table('pms_locations')->update(['name' => 'CHANGED ON BOARD']);
        });

        $result = $this->syncCycle();

        $this->assertSame(1, $result['summary']['pushed']['rejected'], 'only the users insert was even sent');
        $this->assertStringContainsString('Only the cloud creates users', (string) $this->onCloud(fn () => DB::table('sync_inbox')->where('status', 'failed')->value('last_error')));
        $this->assertNull($this->onCloud(fn () => DB::table('users')->where('email', 'x@mvtest.test')->first()));
        $this->assertSame('MV TEST', $this->onCloud(fn () => DB::table('vessels')->where('id', 7)->value('name')));
    }

    public function test_office_changes_to_the_vessel_and_reference_lists_come_down(): void
    {
        $this->onCloud(function () {
            DB::table('vessels')->where('id', 7)->update(['master_name' => 'Capt. Rahman', 'updated_at' => now()]);
            DB::table('vessels')->where('id', 8)->update(['master_name' => 'Not for MV TEST', 'updated_at' => now()]);
            DB::table('pms_job_types')->insert(['name' => 'INSPECTION', 'abbreviation' => 'I', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('ranks')->insert(['code' => '3E', 'name' => 'THIRD ENGINEER', 'department' => 'Engine', 'created_at' => now(), 'updated_at' => now()]);
        });

        $this->assertCycleOk();

        $this->onShip(function () {
            $this->assertSame('Capt. Rahman', DB::table('vessels')->where('id', 7)->value('master_name'));
            $this->assertNotSame('Not for MV TEST', DB::table('vessels')->where('id', 8)->value('master_name'), 'another vessel\'s record stays in the office');
            $this->assertTrue(DB::table('pms_job_types')->where('name', 'INSPECTION')->exists());
            $this->assertTrue(DB::table('ranks')->where('code', '3E')->exists());
        });
    }
}
