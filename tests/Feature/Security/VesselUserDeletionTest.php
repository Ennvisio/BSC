<?php

namespace Tests\Feature\Security;

use App\Support\DeletionPolicy;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Checkpoint 10, ADR-006 and ADR-004, on the application's real schema.
 *
 * ADR-006: vessel users (every ship role) are refused every delete, retire,
 * disable and trash action, on the cloud and on their vessel's server, and
 * the record is left as it was. Office users keep the delete actions they
 * had. The screens stop offering the buttons to vessel users.
 *
 * ADR-004: only a Superadmin blocks a user or sets another user's password;
 * vessel users get no user administration at all.
 *
 * PD-71 A and B (approved 2026-11-23): only a Superadmin creates users,
 * changes a role, and maintains requisition categories.
 *
 * PD-72 (approved 2026-11-23): vessel users keep the three draft edits but
 * cannot deactivate a PMS job. PD-73 (approved 2026-11-24): nor can they
 * mark a PMS component inactive or reactivate it; the office keeps its
 * existing permission.
 */
class VesselUserDeletionTest extends TestCase
{
    private const MYSQL_ONLY = [
        '2026_09_23_110100_add_title_and_category_to_vessel_certificates_table.php',
        '2026_09_23_120000_make_issue_auth_nullable_on_vessel_certificates.php',
        '2026_11_15_100000_add_performance_indexes.php',
    ];

    private const SHIP_ROLES = ['master', 'chief-engineer', 'chief-officer', 'second-engineer', 'operator'];

    private const VESSEL = 7;

    /** @var array<string, int> role => user id */
    private array $users = [];

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['navisuite.mode' => 'cloud', 'navisuite.vessel_id' => null, 'navisuite.support_accounts' => '']);

        foreach (glob(database_path('migrations/*.php')) as $file) {
            $name = basename($file);
            if (str_contains($name, '_sync_') || in_array($name, self::MYSQL_ONLY, true)) {
                continue;
            }
            Artisan::call('migrate', ['--path' => [$file], '--realpath' => true, '--force' => true]);
        }
        Schema::table('vessel_certificates', function (Blueprint $t) {
            $t->string('title')->nullable();
            $t->unsignedBigInteger('category_id')->nullable();
        });

        $this->seedFleet();
    }

    private function seedFleet(): void
    {
        DB::table('vessels')->insert([
            'id' => self::VESSEL, 'name' => 'MV TEST', 'owner_name' => '-', 'owner_address' => '-', 'manager_name' => '-',
            'manager_address' => '-', 'master_name' => '-', 'master_cert_no' => '-', 'master_cert_validity' => '2030-01-01',
            'ch_eng_name' => '-', 'ch_eng_cert_no' => '-', 'ch_eng_cert_validity' => '2030-01-01', 'status' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $password = Hash::make('secret');
        foreach (array_merge(['super-admin', 'gm-srd', 'am-ssm'], self::SHIP_ROLES) as $role) {
            $type = \App\Role::TYPE_BY_ROLE[$role];
            $id = DB::table('users')->insertGetId([
                'name' => ucfirst($role), 'email' => $role.'@test.test', 'password' => $password, 'status' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('roles')->insert([
                'user_id' => $id, 'vessel_id' => $type === 'ship' ? self::VESSEL : null, 'role' => $role, 'user_type' => $type,
                'created_by' => 'seed', 'status' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->users[$role] = $id;
        }

        $this->ids['budget_group'] = DB::table('budget_groups')->insertGetId(['name' => 'Maintenance', 'status' => true, 'created_by' => 'seed', 'created_at' => now(), 'updated_at' => now()]);
        $this->ids['certificate_category'] = DB::table('certificate_categories')->insertGetId(['name' => 'Test category ADR-006', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = (int) DB::table('categories')->value('id') ?: DB::table('categories')->insertGetId(['name' => 'Spares', 'symbol' => 'SPR', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->ids['category'] = $categoryId;
        $this->ids['item'] = DB::table('items')->insertGetId([
            'category_id' => $categoryId, 'impa_code' => 0, 'name' => 'Gasket', 'unit' => 'PC', 'article_number' => 'ADR6-1',
            'item_key' => 'adr6-1', 'created_by' => 'seed', 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->ids['equipment'] = DB::table('vessel_equipment')->insertGetId(['vessel_id' => self::VESSEL, 'name' => 'Main engine', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->ids['attachment'] = DB::table('attachments')->insertGetId([
            'uploaded_by' => $this->users['chief-officer'], 'original_filename' => 'spec.pdf', 'path' => 'attachments/spec.pdf',
            'mime_type' => 'application/pdf', 'title' => 'Spec', 'kind' => 'document', 'file_size' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function actAs(string $role): static
    {
        return $this->actingAs(User::findOrFail($this->users[$role]));
    }

    /** One sample address for each path in DeletionPolicy::DELETE_PATHS. */
    public static function deleteAddresses(): array
    {
        return [
            'budget group' => ['/budget-group/delete'],
            'certificate category' => ['/certificate-category/delete'],
            'catalog item' => ['/item/delete'],
            'requisition category' => ['/category/delete'],
            'PMS master entry' => ['/pms/master/locations/1/delete'],
            'vessel survey' => ['/survey/delete'],
            'vessel certificate' => ['/certificate/delete'],
            'vessel' => ['/vessel/delete'],
            'vessel activation' => ['/vessel/toggle-status'],
            'equipment' => ['/vessel/equipment/delete'],
            'PMS component' => ['/pms/components/1/delete'],
            'PMS reading' => ['/pms/readings/1/delete'],
            'PMS part' => ['/pms/parts/1/delete'],
            'attachment' => ['/attachments/1/delete'],
            'trash permanent delete' => ['/permanent-delete'],
        ];
    }

    public function test_every_listed_path_has_a_sample_address(): void
    {
        $this->assertCount(count(DeletionPolicy::DELETE_PATHS), self::deleteAddresses());
        foreach (self::deleteAddresses() as [$address]) {
            $request = \Illuminate\Http\Request::create($address, 'POST');
            $this->assertTrue(app(DeletionPolicy::class)->isDeleteRequest($request), $address);
        }
    }

    #[DataProvider('deleteAddresses')]
    public function test_every_ship_role_is_refused_on_the_cloud(string $address): void
    {
        foreach (self::SHIP_ROLES as $role) {
            $this->actAs($role)
                ->postJson($address, ['id' => 1, 'type' => 'item', 'active' => 0])
                ->assertForbidden()
                ->assertJson(['message' => DeletionPolicy::VESSEL_USER_MESSAGE]);
        }
    }

    #[DataProvider('deleteAddresses')]
    public function test_everyone_is_refused_on_the_vessel_server(string $address): void
    {
        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => (string) self::VESSEL]);

        foreach (self::SHIP_ROLES as $role) {
            // Some of these paths are already office-only on board
            // (EnforceAppMode), which answers first; either way it is refused.
            $this->actAs($role)
                ->postJson($address, ['id' => 1, 'type' => 'item', 'active' => 0])
                ->assertForbidden();
        }
    }

    public function test_vessel_server_message_for_crew(): void
    {
        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => (string) self::VESSEL]);

        $this->actAs('master')->postJson('/budget-group/delete', ['id' => $this->ids['budget_group']])
            ->assertForbidden()
            ->assertJson(['message' => DeletionPolicy::VESSEL_SERVER_MESSAGE]);
    }

    public function test_a_refused_delete_leaves_each_record_unchanged(): void
    {
        $this->actAs('master')->postJson('/budget-group/delete', ['id' => $this->ids['budget_group']])->assertForbidden();
        $this->actAs('chief-engineer')->postJson('/certificate-category/delete', ['id' => $this->ids['certificate_category']])->assertForbidden();
        $this->actAs('chief-officer')->postJson('/item/delete', ['id' => $this->ids['item']])->assertForbidden();
        $this->actAs('second-engineer')->postJson('/category/delete', ['id' => $this->ids['category']])->assertForbidden();
        $this->actAs('master')->postJson('/vessel/equipment/delete', ['id' => $this->ids['equipment']])->assertForbidden();
        $this->actAs('chief-officer')->postJson('/attachments/'.$this->ids['attachment'].'/delete')->assertForbidden();
        $this->actAs('master')->postJson('/vessel/toggle-status', ['id' => self::VESSEL, 'active' => 0])->assertForbidden();
        $this->actAs('master')->postJson('/permanent-delete', ['id' => $this->ids['item'], 'type' => 'item'])->assertForbidden();

        $this->assertTrue((bool) DB::table('budget_groups')->where('id', $this->ids['budget_group'])->value('status'));
        $this->assertTrue((bool) DB::table('certificate_categories')->where('id', $this->ids['certificate_category'])->value('status'));
        $this->assertTrue((bool) DB::table('items')->where('id', $this->ids['item'])->value('status'));
        $this->assertTrue((bool) DB::table('categories')->where('id', $this->ids['category'])->value('status'));
        $this->assertTrue((bool) DB::table('vessel_equipment')->where('id', $this->ids['equipment'])->value('status'));
        $this->assertTrue(DB::table('attachments')->where('id', $this->ids['attachment'])->exists());
        $this->assertTrue((bool) DB::table('vessels')->where('id', self::VESSEL)->value('status'));
    }

    public function test_office_users_keep_their_delete_actions(): void
    {
        $this->actAs('super-admin')->postJson('/budget-group/delete', ['id' => $this->ids['budget_group']])->assertOk();
        $this->actAs('gm-srd')->postJson('/certificate-category/delete', ['id' => $this->ids['certificate_category']])->assertOk();
        $this->actAs('super-admin')->postJson('/vessel/equipment/delete', ['id' => $this->ids['equipment'], 'vessel_id' => self::VESSEL])->assertOk();

        $this->assertFalse((bool) DB::table('budget_groups')->where('id', $this->ids['budget_group'])->value('status'));
        $this->assertFalse((bool) DB::table('certificate_categories')->where('id', $this->ids['certificate_category'])->value('status'));
        $this->assertFalse((bool) DB::table('vessel_equipment')->where('id', $this->ids['equipment'])->value('status'));
    }

    /**
     * PD-71 C (approved 2026-11-23): every office role may edit catalog
     * items. It is not a grant of retirement: an office role that retires
     * items today still does, and ship roles stay refused (ADR-006).
     */
    public function test_every_office_role_edits_catalog_items_and_retirement_is_unchanged(): void
    {
        $officeRoles = array_keys(array_filter(\App\Role::TYPE_BY_ROLE, fn ($type) => $type !== 'ship'));
        $this->assertCount(12, $officeRoles);

        foreach ($officeRoles as $role) {
            if (! isset($this->users[$role])) {
                $id = DB::table('users')->insertGetId(['name' => $role, 'email' => $role.'@test.test', 'password' => 'x', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('roles')->insert(['user_id' => $id, 'vessel_id' => null, 'role' => $role, 'user_type' => \App\Role::TYPE_BY_ROLE[$role], 'created_by' => 'seed', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
                $this->users[$role] = $id;
            }
            $this->app['auth']->forgetGuards();
            $this->actAs($role)->postJson('/item/update', [
                'item_id' => $this->ids['item'], 'Item_Name' => 'Gasket by '.$role, 'Impa_Code_No' => '000',
                'Measurement_Unit' => 'PC', 'Category_Name' => $this->ids['category'],
            ])->assertOk();
            $this->assertSame('Gasket by '.$role, DB::table('items')->where('id', $this->ids['item'])->value('name'), $role.' edits a catalog item');
        }

        $this->app['auth']->forgetGuards();
        $this->actAs('chief-officer')->postJson('/item/delete', ['id' => $this->ids['item']])->assertForbidden();
        $this->assertTrue((bool) DB::table('items')->where('id', $this->ids['item'])->value('status'));

        $this->app['auth']->forgetGuards();
        $this->actAs('am-ssm')->postJson('/item/delete', ['id' => $this->ids['item']])->assertOk();
        $this->assertFalse((bool) DB::table('items')->where('id', $this->ids['item'])->value('status'), 'office retirement as before');
    }

    public function test_vessel_users_still_create_and_update_master_data(): void
    {
        $this->actAs('master')->postJson('/budget-group/store', ['name' => 'Deck stores', 'kind' => 'item'])->assertOk();
        $this->actAs('master')->postJson('/budget-group/update', ['BudgetGroup_Id' => $this->ids['budget_group'], 'name' => 'Maintenance and repair', 'kind' => 'item'])->assertOk();
        $this->actAs('chief-engineer')->postJson('/certificate-category/store', ['name' => 'Pollution'])->assertSuccessful();

        $this->assertTrue(DB::table('budget_groups')->where('name', 'Deck stores')->exists());
        $this->assertSame('Maintenance and repair', DB::table('budget_groups')->where('id', $this->ids['budget_group'])->value('name'));
        $this->assertTrue(DB::table('certificate_categories')->where('name', 'Pollution')->exists());
    }

    public function test_draft_edits_awaiting_pd72_are_not_refused_by_the_policy(): void
    {
        foreach ([
            '/requisition/1/items/1/remove',
            '/requisition/1/items/1/attachments/1/detach',
            '/pms/parts/1/remove-from-requisition',
            '/user/delete',
            '/restore',
        ] as $address) {
            $this->assertFalse(app(DeletionPolicy::class)->isDeleteRequest(\Illuminate\Http\Request::create($address, 'POST')), $address);
        }
    }

    public function test_reading_pages_is_unaffected(): void
    {
        $this->actAs('master')->get('/home/budget-group')->assertOk();
    }

    // ---- The screens -------------------------------------------------------

    public function test_ship_users_are_not_offered_delete_buttons(): void
    {
        $this->actAs('master')->get('/home/budget-group')->assertOk()
            ->assertSee('.delete-budget-group, .delete-cert-category', false);
        $this->actAs('chief-engineer')->get('/home/certificate-category')->assertOk()
            ->assertSee('.delete-budget-group, .delete-cert-category', false);
    }

    public function test_office_users_are_still_offered_delete_buttons(): void
    {
        $this->actAs('super-admin')->get('/home/budget-group')->assertOk()
            ->assertDontSee('.delete-budget-group, .delete-cert-category', false)
            ->assertSee('delete-budget-group', false);
    }

    // ---- ADR-004: user administration --------------------------------------

    public function test_vessel_users_get_no_user_administration(): void
    {
        $target = $this->users['operator'];
        foreach (self::SHIP_ROLES as $role) {
            $this->actAs($role)->get('/home/user')->assertForbidden();
            $this->actAs($role)->getJson('/user/'.$target)->assertForbidden();
            $this->actAs($role)->postJson('/user/delete', ['id' => $target])->assertForbidden();
            $_POST['user_id'] = $target;
            $this->actAs($role)->postJson('/user/update', $this->userUpdate($target, ['password' => 'newpass1', 'password_confirmation' => 'newpass1', 'User_Role' => 'super-admin']))->assertForbidden();
            $this->actAs($role)->postJson('/user/store', [
                'User_Name' => 'X', 'email' => $role.'-new@test.test', 'password' => 'newpass1', 'password_confirmation' => 'newpass1',
                'User_Role' => 'super-admin', 'Vessel_Name' => 0, 'user_type' => 'admin',
            ])->assertForbidden();
        }

        $this->assertSame('operator', DB::table('roles')->where('user_id', $target)->value('role'));
        $this->assertTrue((bool) DB::table('users')->where('id', $target)->value('status'));
        $this->assertTrue(Hash::check('secret', DB::table('users')->where('id', $target)->value('password')));
        $this->assertFalse(DB::table('roles')->where('role', 'super-admin')->where('user_id', '!=', $this->users['super-admin'])->exists());
    }

    public function test_only_a_superadmin_blocks_a_user(): void
    {
        $target = $this->users['operator'];

        $this->actAs('gm-srd')->postJson('/user/delete', ['id' => $target])->assertForbidden();
        $this->actAs('am-ssm')->postJson('/user/delete', ['id' => $target])->assertForbidden();
        $this->assertTrue((bool) DB::table('users')->where('id', $target)->value('status'));

        $this->actAs('super-admin')->postJson('/user/delete', ['id' => $target])->assertOk();
        $this->assertFalse((bool) DB::table('users')->where('id', $target)->value('status'));
    }

    public function test_only_a_superadmin_sets_another_users_password(): void
    {
        $target = $this->users['operator'];
        $_POST['user_id'] = $target;
        $withPassword = $this->userUpdate($target, ['password' => 'newpass1', 'password_confirmation' => 'newpass1']);

        $this->actAs('gm-srd')->postJson('/user/update', $withPassword)->assertForbidden();
        $this->assertTrue(Hash::check('secret', DB::table('users')->where('id', $target)->value('password')));

        $this->actAs('super-admin')->postJson('/user/update', $withPassword)->assertOk();
        $this->assertTrue(Hash::check('newpass1', DB::table('users')->where('id', $target)->value('password')));
    }

    public function test_office_edit_without_a_password_is_unchanged(): void
    {
        // Which office roles may edit users is PD-71 A; until then the
        // existing behaviour stays for everything but blocks and passwords.
        $target = $this->users['operator'];
        $_POST['user_id'] = $target;

        $this->actAs('gm-srd')->postJson('/user/update', $this->userUpdate($target, ['User_Name' => 'Renamed']))->assertOk();
        $this->assertSame('Renamed', DB::table('users')->where('id', $target)->value('name'));
        $this->assertTrue(Hash::check('secret', DB::table('users')->where('id', $target)->value('password')));
    }

    public function test_users_screen_offers_block_and_password_only_to_a_superadmin(): void
    {
        $this->actAs('gm-srd')->get('/home/user')->assertOk()
            ->assertDontSee('New Password')
            ->assertDontSee('class="btn btn-danger delete-user"', false)
            ->assertSee('.delete-user { display: none !important; }', false);

        $this->actAs('super-admin')->get('/home/user')->assertOk()
            ->assertSee('New Password')
            ->assertDontSee('.delete-user { display: none !important; }', false);
    }

    // ---- PD-71 A: users created and roles changed by a Superadmin only ----

    public function test_only_a_superadmin_creates_users(): void
    {
        $new = fn (string $email) => [
            'User_Name' => 'New', 'email' => $email, 'password' => 'newpass1', 'password_confirmation' => 'newpass1',
            'User_Role' => 'technical-superintendent', 'Vessel_Name' => 0, 'user_type' => 'srd',
        ];
        $this->actAs('gm-srd')->postJson('/user/store', $new('a@test.test'))->assertForbidden();
        $this->actAs('am-ssm')->postJson('/user/store', $new('b@test.test'))->assertForbidden();
        $this->assertFalse(DB::table('users')->whereIn('email', ['a@test.test', 'b@test.test'])->exists());

        $this->actAs('super-admin')->postJson('/user/store', $new('c@test.test'))->assertOk();
        $this->assertTrue(DB::table('users')->where('email', 'c@test.test')->exists());
    }

    public function test_only_a_superadmin_changes_a_role_or_vessel(): void
    {
        DB::table('vessels')->insert(['id' => 8, 'name' => 'MV OTHER', 'owner_name' => '-', 'owner_address' => '-', 'manager_name' => '-',
            'manager_address' => '-', 'master_name' => '-', 'master_cert_no' => '-', 'master_cert_validity' => '2030-01-01',
            'ch_eng_name' => '-', 'ch_eng_cert_no' => '-', 'ch_eng_cert_validity' => '2030-01-01', 'status' => true,
            'created_at' => now(), 'updated_at' => now()]);
        $target = $this->users['operator'];
        $_POST['user_id'] = $target;

        foreach (['gm-srd', 'am-ssm'] as $role) {
            $this->actAs($role)->postJson('/user/update', $this->userUpdate($target, ['User_Role' => 'super-admin', 'Vessel_Name' => 0]))->assertForbidden();
            $this->actAs($role)->postJson('/user/update', $this->userUpdate($target, ['User_Role' => 'master']))->assertForbidden();
            $this->actAs($role)->postJson('/user/update', $this->userUpdate($target, ['Vessel_Name' => 8]))->assertForbidden();
        }
        $this->assertSame('operator', DB::table('roles')->where('user_id', $target)->value('role'));
        $this->assertSame(self::VESSEL, (int) DB::table('roles')->where('user_id', $target)->value('vessel_id'));

        $this->actAs('super-admin')->postJson('/user/update', $this->userUpdate($target, ['User_Role' => 'master', 'Vessel_Name' => 8]))->assertOk();
        $this->assertSame('master', DB::table('roles')->where('user_id', $target)->value('role'));
        $this->assertSame(8, (int) DB::table('roles')->where('user_id', $target)->value('vessel_id'));
    }

    public function test_users_screen_offers_add_and_role_change_only_to_a_superadmin(): void
    {
        $this->actAs('gm-srd')->get('/home/user')->assertOk()
            ->assertDontSee('data-target="#myModal"> <i class="fas fa-plus-square"></i> Add New User', false)
            ->assertSee('#edit_template_modal .funkyradio, #edit_template_modal .User_Role', false);
        $this->actAs('super-admin')->get('/home/user')->assertOk()
            ->assertSee('data-target="#myModal"> <i class="fas fa-plus-square"></i> Add New User', false)
            ->assertDontSee('#edit_template_modal .funkyradio, #edit_template_modal .User_Role', false);
    }

    // ---- PD-71 B: requisition categories by a Superadmin only --------------

    public function test_only_a_superadmin_maintains_requisition_categories(): void
    {
        $id = $this->ids['category'];
        foreach (['gm-srd', 'am-ssm', 'master'] as $i => $role) {
            $this->actAs($role)->postJson('/category/store', ['name' => 'New '.$i, 'symbol' => 'N'.$i])->assertForbidden();
            $this->actAs($role)->postJson('/category/update', ['Category_Id' => $id, 'name' => 'Changed '.$i, 'symbol' => 'C'.$i])->assertForbidden();
            $this->actAs($role)->postJson('/category/delete', ['id' => $id])->assertForbidden();
        }
        $this->assertFalse(DB::table('categories')->where('name', 'like', 'New %')->exists());
        $this->assertTrue((bool) DB::table('categories')->where('id', $id)->value('status'));
        $this->assertStringNotContainsString('Changed', (string) DB::table('categories')->where('id', $id)->value('name'));

        $this->actAs('super-admin')->postJson('/category/store', ['name' => 'Chemicals X', 'symbol' => 'CHX'])->assertOk();
        $this->actAs('super-admin')->postJson('/category/update', ['Category_Id' => $id, 'name' => 'Renamed X', 'symbol' => 'RNX'])->assertOk();
        $this->actAs('super-admin')->postJson('/category/delete', ['id' => $id])->assertOk();
        $this->assertTrue(DB::table('categories')->where('name', 'Chemicals X')->exists());
        $this->assertFalse((bool) DB::table('categories')->where('id', $id)->value('status'));
    }

    public function test_category_screen_offers_maintenance_only_to_a_superadmin(): void
    {
        $this->actAs('gm-srd')->get('/home/category')->assertOk()
            ->assertDontSee('data-target="#myModal"> <i class="fas fa-plus-square"></i> Add New Category', false)->assertDontSee('btn btn-info edit-category', false);
        $this->actAs('super-admin')->get('/home/category')->assertOk()
            ->assertSee('data-target="#myModal"> <i class="fas fa-plus-square"></i> Add New Category', false)->assertSee('btn btn-info edit-category', false);
    }

    // ---- PD-72: PMS deactivation; draft edits stay --------------------------

    private function seedPms(): array
    {
        $location = DB::table('pms_locations')->insertGetId(['name' => 'ENGINE ROOM X', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $category = DB::table('pms_component_categories')->insertGetId(['name' => 'Engine X', 'created_at' => now(), 'updated_at' => now()]);
        $condition = (int) DB::table('pms_conditions')->value('id') ?: DB::table('pms_conditions')->insertGetId(['name' => 'Good X', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
        $jobType = DB::table('pms_job_types')->insertGetId(['name' => 'OVERHAUL X', 'abbreviation' => 'OX', 'created_at' => now(), 'updated_at' => now()]);
        $rank = (int) DB::table('ranks')->value('id') ?: DB::table('ranks')->insertGetId(['code' => 'CE', 'name' => 'CHIEF ENGINEER', 'department' => 'Engine', 'created_at' => now(), 'updated_at' => now()]);
        $component = (string) \Illuminate\Support\Str::uuid();
        DB::table('pms_components')->insert([
            'id' => $component, 'vessel_id' => self::VESSEL, 'location_id' => $location, 'name' => 'MAIN ENGINE X',
            'category_id' => $category, 'condition_id' => $condition, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['component' => $component, 'job_type' => $jobType, 'rank' => $rank, 'location' => $location, 'category' => $category, 'condition' => $condition];
    }

    private function jobPayload(array $pms, bool $active): array
    {
        return [
            'name' => 'Overhaul', 'job_type_id' => $pms['job_type'], 'rank_id' => $pms['rank'],
            'performed_by' => 'Crew', 'perform_when' => 'Anytime', 'warning_period' => 1, 'warning_unit' => 'Weeks',
            'intervals' => [['every' => 6, 'period' => 'Month']], 'is_active' => $active ? 1 : 0,
        ];
    }

    public function test_vessel_users_cannot_deactivate_a_pms_job(): void
    {
        $pms = $this->seedPms();
        $this->actAs('chief-engineer')->postJson('/pms/components/'.$pms['component'].'/job-definitions', $this->jobPayload($pms, true))->assertOk();
        $definition = (string) DB::table('pms_job_definitions')->value('id');

        foreach (['master', 'chief-engineer', 'chief-officer', 'second-engineer'] as $role) {
            $this->actAs($role)->postJson('/pms/job-definitions/'.$definition.'/update', $this->jobPayload($pms, false))->assertForbidden();
            // Leaving the box out is read as "inactive", so it is refused too.
            $payload = $this->jobPayload($pms, true);
            unset($payload['is_active']);
            $this->actAs($role)->postJson('/pms/job-definitions/'.$definition.'/update', $payload)->assertForbidden();
        }
        $this->assertTrue((bool) DB::table('pms_job_definitions')->where('id', $definition)->value('is_active'));

        // Editing an active job is still allowed.
        $this->actAs('master')->postJson('/pms/job-definitions/'.$definition.'/update', ['name' => 'Overhaul 2'] + $this->jobPayload($pms, true))->assertOk();

        // The office can deactivate; the vessel can then reactivate.
        $this->actAs('super-admin')->postJson('/pms/job-definitions/'.$definition.'/update', $this->jobPayload($pms, false))->assertOk();
        $this->assertFalse((bool) DB::table('pms_job_definitions')->where('id', $definition)->value('is_active'));
        $this->actAs('master')->postJson('/pms/job-definitions/'.$definition.'/update', $this->jobPayload($pms, true))->assertOk();
        $this->assertTrue((bool) DB::table('pms_job_definitions')->where('id', $definition)->value('is_active'));

        $this->actAs('master')->getJson('/pms/job-definitions/'.$definition)->assertOk()->assertJson(['can_edit' => true, 'can_deactivate' => false]);
        $this->actAs('super-admin')->getJson('/pms/job-definitions/'.$definition)->assertOk()->assertJson(['can_deactivate' => true]);
    }

    // ------------------------------------------------ PD-73 (2026-11-24)

    private function componentPayload(array $pms, ?bool $inactive, string $name = 'MAIN ENGINE X'): array
    {
        $payload = [
            'name' => $name, 'location_id' => $pms['location'], 'category_id' => $pms['category'],
            'condition_id' => $pms['condition'], 'counter_mode' => 'none', 'sort_order' => 0,
        ];
        if ($inactive !== null) {
            $payload['is_inactive'] = $inactive ? 1 : 0;
        }

        return $payload;
    }

    private function componentInactive(string $id): bool
    {
        return (bool) DB::table('pms_components')->where('id', $id)->value('is_inactive');
    }

    public function test_the_office_keeps_its_permission_to_mark_a_component_inactive_and_active(): void
    {
        $pms = $this->seedPms();
        $url = '/pms/components/'.$pms['component'].'/update';

        $this->actAs('super-admin')->postJson($url, $this->componentPayload($pms, true))->assertOk();
        $this->assertTrue($this->componentInactive($pms['component']));
        $this->actAs('super-admin')->postJson($url, $this->componentPayload($pms, false))->assertOk();
        $this->assertFalse($this->componentInactive($pms['component']));
        // As before, leaving the box out is "active" for the office.
        $this->actAs('super-admin')->postJson($url, $this->componentPayload($pms, true))->assertOk();
        $this->actAs('super-admin')->postJson($url, $this->componentPayload($pms, null))->assertOk();
        $this->assertFalse($this->componentInactive($pms['component']));

        // Unchanged: shore viewers never edited components, and the office
        // copy of a vessel with its own server is read-only (PD-06).
        $this->actAs('gm-srd')->postJson($url, $this->componentPayload($pms, true))->assertForbidden();
        DB::table('vessels')->where('id', self::VESSEL)->update(['offline_enabled_at' => now()]);
        $this->actAs('super-admin')->postJson($url, $this->componentPayload($pms, true))->assertForbidden();
        $this->assertFalse($this->componentInactive($pms['component']));
    }

    public function test_vessel_users_cannot_mark_a_component_inactive_or_reactivate_it(): void
    {
        $pms = $this->seedPms();
        $url = '/pms/components/'.$pms['component'].'/update';

        foreach (['master', 'chief-engineer', 'chief-officer', 'second-engineer'] as $role) {
            $this->actAs($role)->postJson($url, $this->componentPayload($pms, true))
                ->assertForbidden()->assertJson(['message' => \App\Http\Controllers\PmsComponentController::INACTIVE_REFUSED]);
        }
        $this->assertFalse($this->componentInactive($pms['component']));

        $this->actAs('super-admin')->postJson($url, $this->componentPayload($pms, true))->assertOk();
        foreach (['master', 'chief-engineer', 'chief-officer', 'second-engineer'] as $role) {
            $this->actAs($role)->postJson($url, $this->componentPayload($pms, false))->assertForbidden();
        }
        $this->assertTrue($this->componentInactive($pms['component']), 'still inactive: reactivating is refused too');

        // A new component cannot start inactive either.
        $this->actAs('master')->postJson('/pms/components', $this->componentPayload($pms, true, 'NEW PUMP'))->assertForbidden();
        $this->assertSame(0, DB::table('pms_components')->where('name', 'NEW PUMP')->count());
    }

    public function test_vessel_users_still_make_ordinary_component_edits(): void
    {
        $pms = $this->seedPms();
        $url = '/pms/components/'.$pms['component'].'/update';

        // The screen sends no is_inactive for them (the box is disabled).
        $this->actAs('master')->postJson($url, $this->componentPayload($pms, null, 'MAIN ENGINE Y') + ['serial_no' => 'SN-1', 'remark' => 'Checked'])->assertOk();
        $row = DB::table('pms_components')->where('id', $pms['component'])->first();
        $this->assertSame(['MAIN ENGINE Y', 'SN-1', 'Checked', 0], [$row->name, $row->serial_no, $row->remark, (int) $row->is_inactive]);

        // On an inactive component, an edit leaves it inactive.
        $this->actAs('super-admin')->postJson($url, $this->componentPayload($pms, true, 'MAIN ENGINE Y'))->assertOk();
        $this->actAs('chief-engineer')->postJson($url, $this->componentPayload($pms, null, 'MAIN ENGINE Z'))->assertOk();
        $this->assertTrue($this->componentInactive($pms['component']));
        $this->assertSame('MAIN ENGINE Z', DB::table('pms_components')->where('id', $pms['component'])->value('name'));

        // Sending the value it already has is not a change, so it is allowed.
        $this->actAs('master')->postJson($url, $this->componentPayload($pms, true, 'MAIN ENGINE W'))->assertOk();
        $this->assertTrue($this->componentInactive($pms['component']));

        // A new component starts active.
        $this->actAs('master')->postJson('/pms/components', $this->componentPayload($pms, null, 'NEW PUMP'))->assertOk();
        $this->assertSame(0, (int) DB::table('pms_components')->where('name', 'NEW PUMP')->value('is_inactive'));
    }

    public function test_the_inactive_box_is_disabled_for_vessel_users_on_screen(): void
    {
        $this->seedPms();

        $this->actAs('master')->get('/pms/components?vessel_id=7')->assertOk()
            ->assertSee('data-office-only="1"', false)->assertSee('Set by the office only.');
        $this->actAs('super-admin')->get('/pms/components?vessel_id=7')->assertOk()
            ->assertSee('id="pc-inactive"', false)->assertDontSee('data-office-only="1"', false)->assertDontSee('Set by the office only.');
    }

    public function test_nobody_changes_the_switch_on_a_vessel_server(): void
    {
        $pms = $this->seedPms();
        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => self::VESSEL]);

        $this->actAs('master')->postJson('/pms/components/'.$pms['component'].'/update', $this->componentPayload($pms, true))->assertForbidden();
        $this->actAs('master')->postJson('/pms/components/'.$pms['component'].'/update', $this->componentPayload($pms, null, 'MAIN ENGINE V'))->assertOk();
        $this->assertFalse($this->componentInactive($pms['component']));
    }

    private function userUpdate(int $userId, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $userId, 'User_Name' => 'Operator', 'email' => 'operator@test.test',
            'User_Role' => 'operator', 'Vessel_Name' => self::VESSEL, 'user_type' => 'ship',
        ], $overrides);
    }

    protected function tearDown(): void
    {
        unset($_POST['user_id']);
        parent::tearDown();
    }
}
