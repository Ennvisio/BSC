<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\MemberMiddleWare;
use App\Http\Middleware\RefuseVesselUserDeletes;
use App\Support\TrashGuard;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Checkpoint 10, items 4 to 7, on the application's real schema. Each was
 * approved on 2026-11-23 as recommended:
 *
 * - PD-06: the office copy of an offline vessel's equipment list is
 *   read-only on the cloud, as its PMS records already are.
 * - PD-60: the trash's permanent delete is refused while anything refers
 *   to the record, and for any record of an offline vessel.
 * - PD-61: a file on a requisition line, a procurement stage or a
 *   consumption record is not deleted; any other delete hides the file.
 * - PD-62: every certificate copy is kept.
 */
class ApprovedGuardsTest extends TestCase
{
    private const MYSQL_ONLY = [
        '2026_09_23_110100_add_title_and_category_to_vessel_certificates_table.php',
        '2026_09_23_120000_make_issue_auth_nullable_on_vessel_certificates.php',
        '2026_11_15_100000_add_performance_indexes.php',
    ];

    private const VESSEL = 7;

    /** @var array<string, int> */
    private array $users = [];

    private string $publicRoot;

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
            $t->string('issue_auth')->nullable()->change();
            $t->unsignedInteger('certificate_id')->nullable()->change();   // as the MySQL-only migration leaves it
        });

        $this->publicRoot = sys_get_temp_dir().'/bsc-guards-'.uniqid();
        mkdir($this->publicRoot.'/images/cert_copy', 0777, true);
        config(['filesystems.disks.public_images.root' => $this->publicRoot]);

        $this->vessel(self::VESSEL, 'MV TEST');
        $password = Hash::make('secret');
        foreach (['super-admin' => null, 'am-ssm' => null, 'chief-officer' => self::VESSEL, 'master' => self::VESSEL] as $role => $vessel) {
            $id = DB::table('users')->insertGetId(['name' => $role, 'email' => $role.'@test.test', 'password' => $password, 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('roles')->insert([
                'user_id' => $id, 'vessel_id' => $vessel, 'role' => $role, 'user_type' => \App\Role::TYPE_BY_ROLE[$role],
                'created_by' => 'seed', 'status' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->users[$role] = $id;
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->publicRoot));
        parent::tearDown();
    }

    private function vessel(int $id, string $name, bool $status = true): void
    {
        DB::table('vessels')->insert([
            'id' => $id, 'name' => $name, 'owner_name' => '-', 'owner_address' => '-', 'manager_name' => '-',
            'manager_address' => '-', 'master_name' => '-', 'master_cert_no' => '-', 'master_cert_validity' => '2030-01-01',
            'ch_eng_name' => '-', 'ch_eng_cert_no' => '-', 'ch_eng_cert_validity' => '2030-01-01', 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function offline(int $vesselId): void
    {
        DB::table('vessels')->where('id', $vesselId)->update(['offline_enabled_at' => now()]);
    }

    private function actAs(string $role): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs(User::findOrFail($this->users[$role]));
    }

    // ------------------------------------------------------------- PD-06

    public function test_pd06_the_office_edits_equipment_of_a_vessel_without_its_own_server(): void
    {
        $id = DB::table('vessel_equipment')->insertGetId(['vessel_id' => self::VESSEL, 'name' => 'Boiler', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->actAs('super-admin')->postJson('/vessel/equipment/update', ['vessel_id' => self::VESSEL, 'id' => $id, 'name' => 'Aux boiler'])->assertOk();
        $this->actAs('super-admin')->postJson('/vessel/equipment/store', ['vessel_id' => self::VESSEL, 'name' => 'Purifier'])->assertOk();

        $this->assertSame('Aux boiler', DB::table('vessel_equipment')->where('id', $id)->value('name'));
        $this->assertSame(2, DB::table('vessel_equipment')->count());
    }

    public function test_pd06_the_office_copy_of_an_offline_vessels_equipment_is_read_only(): void
    {
        $id = DB::table('vessel_equipment')->insertGetId(['vessel_id' => self::VESSEL, 'name' => 'Boiler', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->offline(self::VESSEL);

        $this->actAs('super-admin')->postJson('/vessel/equipment/store', ['vessel_id' => self::VESSEL, 'name' => 'Purifier'])->assertForbidden();
        $this->actAs('super-admin')->postJson('/vessel/equipment/update', ['vessel_id' => self::VESSEL, 'id' => $id, 'name' => 'Aux boiler'])->assertForbidden();
        $this->actAs('super-admin')->postJson('/vessel/equipment/delete', ['vessel_id' => self::VESSEL, 'id' => $id])->assertForbidden();
        $this->actAs('super-admin')->post('/vessel/equipment/upload', [
            'vessel_id' => self::VESSEL, 'equipment_file' => UploadedFile::fake()->create('list.csv', 1, 'text/csv'),
        ])->assertForbidden();

        $row = DB::table('vessel_equipment')->where('id', $id)->first();
        $this->assertSame(['Boiler', 1], [$row->name, (int) $row->status]);
        $this->assertSame(1, DB::table('vessel_equipment')->count());

        $page = $this->actAs('super-admin')->get('/vessel/equipment?vessel_id='.self::VESSEL)->assertOk();
        $page->assertSee('The office copy is read-only.');
        $page->assertDontSee('data-target="#add-equipment-modal"', false);
        $page->assertDontSee('class="btn btn-danger delete-equipment"', false);
        $page->assertDontSee('class="btn btn-info edit-equipment"', false);
    }

    // ------------------------------------------------------------- PD-60

    private function retiredCategory(string $name = 'Old spares'): int
    {
        return DB::table('categories')->insertGetId(['name' => $name, 'symbol' => strtoupper(substr(md5($name), 0, 3)), 'status' => false, 'created_by' => 'seed', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function item(int $categoryId, bool $status): int
    {
        return DB::table('items')->insertGetId([
            'category_id' => $categoryId, 'impa_code' => 0, 'name' => 'Gasket', 'unit' => 'PC', 'article_number' => 'G-'.uniqid(),
            'item_key' => uniqid(), 'created_by' => 'seed', 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function trashDelete(string $type, int $id, string $role = 'super-admin')
    {
        return $this->actAs($role)->postJson('/permanent-delete', ['type' => $type, 'id' => $id]);
    }

    public function test_pd60_a_retired_record_nothing_refers_to_is_deleted(): void
    {
        $id = $this->retiredCategory();

        $this->trashDelete('category', $id)->assertOk()->assertJson(['deleted' => true]);
        $this->assertNull(DB::table('categories')->find($id));
    }

    public function test_pd60_a_record_others_refer_to_stays_retired_in_the_trash(): void
    {
        $category = $this->retiredCategory();
        $item = $this->item($category, false);
        DB::table('vessel_items')->insert(['vessel_id' => self::VESSEL, 'item_id' => $item, 'stock_qty' => 4, 'created_at' => now(), 'updated_at' => now()]);

        $this->trashDelete('item', $item)->assertStatus(422)->assertJson(['deleted' => false])->assertJsonFragment(['message' => app(TrashGuard::class)->refusal('item', $item)]);
        $this->trashDelete('category', $category)->assertStatus(422);

        $this->assertNotNull(DB::table('items')->find($item));
        $this->assertNotNull(DB::table('categories')->find($category));
        $this->assertSame(1, DB::table('vessel_items')->where('item_id', $item)->count(), 'the vessel\'s stock link is untouched');
        $this->assertStringContainsString('vessel items', app(TrashGuard::class)->refusal('item', $item));
    }

    /** Your decision of 2026-11-23: the office never deletes an offline vessel's stock link. */
    public function test_the_office_cannot_delete_an_offline_vessels_stock_link_through_the_trash(): void
    {
        $category = $this->retiredCategory();
        $item = $this->item($category, false);
        DB::table('vessel_items')->insert(['vessel_id' => self::VESSEL, 'item_id' => $item, 'stock_qty' => 4, 'created_at' => now(), 'updated_at' => now()]);
        $this->offline(self::VESSEL);
        DB::table('vessels')->where('id', self::VESSEL)->update(['status' => false]);

        $this->trashDelete('item', $item)->assertStatus(422)->assertJson(['deleted' => false]);
        $this->trashDelete('vessel', self::VESSEL)->assertStatus(422)->assertJson(['deleted' => false]);

        $this->assertSame(4, (int) DB::table('vessel_items')->where('item_id', $item)->value('stock_qty'), 'the stock link and its stock are untouched');
        $this->assertNotNull(DB::table('items')->find($item));
        $this->assertNotNull(DB::table('vessels')->find(self::VESSEL));
    }

    public function test_an_offline_vessels_stock_link_is_never_deleted_through_the_model_on_the_cloud(): void
    {
        $item = $this->item($this->retiredCategory(), true);
        DB::table('vessel_items')->insert(['vessel_id' => self::VESSEL, 'item_id' => $item, 'stock_qty' => 4, 'created_at' => now(), 'updated_at' => now()]);
        $this->offline(self::VESSEL);

        try {
            \App\VesselItem::where('vessel_id', self::VESSEL)->firstOrFail()->delete();
            $this->fail('the delete should be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('own onboard server', $e->getMessage());
        }
        $this->assertSame(1, DB::table('vessel_items')->where('vessel_id', self::VESSEL)->count());
    }

    public function test_the_stock_link_backstop_leaves_other_vessels_and_the_vessel_server_alone(): void
    {
        $item = $this->item($this->retiredCategory(), true);
        DB::table('vessel_items')->insert(['vessel_id' => self::VESSEL, 'item_id' => $item, 'stock_qty' => 4, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertFalse(\App\VesselItem::lockedOnCloud(self::VESSEL), 'a vessel without its own server is as before');

        $this->offline(self::VESSEL);
        $this->assertTrue(\App\VesselItem::lockedOnCloud(self::VESSEL));
        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => self::VESSEL]);
        $this->assertFalse(\App\VesselItem::lockedOnCloud(self::VESSEL), 'the vessel server keeps its own rules');
        config(['navisuite.mode' => 'cloud', 'navisuite.cloud.lock_offline_vessels' => false]);
        $this->assertFalse(\App\VesselItem::lockedOnCloud(self::VESSEL), 'the existing switch turns every offline lock off together');
    }

    public function test_pd60_only_a_record_in_the_trash_can_be_deleted_permanently(): void
    {
        $category = DB::table('categories')->insertGetId(['name' => 'In use', 'symbol' => 'USE', 'status' => true, 'created_by' => 'seed', 'created_at' => now(), 'updated_at' => now()]);

        $this->trashDelete('category', $category)->assertStatus(422);
        $this->trashDelete('nonsense', $category)->assertStatus(422);
        $this->assertNotNull(DB::table('categories')->find($category));
    }

    public function test_pd60_a_record_of_a_vessel_with_its_own_server_is_never_deleted_here(): void
    {
        $survey = $this->vesselSurvey(self::VESSEL);
        $this->offline(self::VESSEL);

        $this->trashDelete('survey', $survey)->assertStatus(422);
        $this->assertNotNull(DB::table('vessel_surveys')->find($survey));
        $this->assertStringContainsString('own onboard server', app(TrashGuard::class)->refusal('survey', $survey));
    }

    public function test_pd60_a_vessel_survey_deletes_that_survey_not_the_survey_type_with_the_same_id(): void
    {
        $type = DB::table('surveys')->insertGetId(['name' => 'Annual', 'created_by' => 'seed', 'created_at' => now(), 'updated_at' => now()]);
        $other = DB::table('surveys')->insertGetId(['name' => 'Intermediate', 'created_by' => 'seed', 'created_at' => now(), 'updated_at' => now()]);
        $survey = $this->vesselSurvey(self::VESSEL, $type);
        $this->assertSame($type, $survey, 'same id in both tables: the case that used to delete the survey type');

        $this->trashDelete('survey', $survey)->assertOk()->assertJson(['deleted' => true]);

        $this->assertNull(DB::table('vessel_surveys')->find($survey));
        $this->assertNotNull(DB::table('surveys')->find($type), 'the fleet-wide survey type is untouched');
        $this->assertNotNull(DB::table('surveys')->find($other));
    }

    public function test_pd60_a_vessel_with_records_cannot_be_deleted(): void
    {
        $this->vessel(9, 'MV RETIRED', false);
        DB::table('vessel_equipment')->insert(['vessel_id' => 9, 'name' => 'Boiler', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->vessel(10, 'MV EMPTY', false);

        $this->trashDelete('vessel', 9)->assertStatus(422);
        $this->trashDelete('vessel', 10)->assertOk();
        $this->assertNotNull(DB::table('vessels')->find(9));
        $this->assertNull(DB::table('vessels')->find(10));
    }

    public function test_pd60_a_retired_requisition_and_certificate_nothing_refers_to_are_deleted(): void
    {
        $order = DB::table('orders')->insertGetId([
            'vessel_id' => self::VESSEL, 'category_id' => $this->retiredCategory('Req category'), 'req_no' => 'PD60/1',
            'req_date' => '2026-11-20', 'port_name' => 'Port', 'status' => 'draft', 'ord_status' => false, 'created_by' => 1,
        ]);
        $certificate = DB::table('vessel_certificates')->insertGetId([
            'vessel_id' => self::VESSEL, 'title' => 'Old registry', 'issue_date' => '2025-01-01', 'status' => false,
            'created_by' => $this->users['super-admin'], 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->trashDelete('order', $order)->assertOk()->assertJson(['deleted' => true]);
        $this->trashDelete('certificate', $certificate)->assertOk()->assertJson(['deleted' => true]);

        $this->assertNull(DB::table('orders')->find($order));
        $this->assertNull(DB::table('vessel_certificates')->find($certificate));
    }

    public function test_trash_restore_refuses_an_unsubmitted_draft_and_changes_nothing(): void
    {
        $this->vessel(11, 'MV LAID UP', false);
        $category = $this->retiredCategory('Draft category');
        $draft = DB::table('orders')->insertGetId([
            'vessel_id' => 11, 'category_id' => $category, 'req_no' => '', 'req_date' => '2026-11-20', 'port_name' => '',
            'status' => 'draft', 'ord_status' => false, 'created_by' => 1,
        ]);

        $this->actAs('super-admin')->postJson('/restore', ['type' => 'order', 'id' => $draft])
            ->assertStatus(422)->assertJsonFragment(['message' => 'This is an unsubmitted draft, not a retired requisition. Its author finishes and submits it from the requisition screens; it cannot be restored from the trash.']);

        $this->assertFalse((bool) DB::table('orders')->where('id', $draft)->value('ord_status'), 'the draft is not made a live requisition');
        $this->assertFalse((bool) DB::table('vessels')->where('id', 11)->value('status'), 'nothing else is reactivated first');
        $this->assertFalse((bool) DB::table('categories')->where('id', $category)->value('status'));
    }

    public function test_trash_restore_brings_back_a_retired_submitted_requisition(): void
    {
        $category = $this->retiredCategory('Retired req category');
        $order = DB::table('orders')->insertGetId([
            'vessel_id' => self::VESSEL, 'category_id' => $category, 'req_no' => 'MV/OLD/01/2026', 'req_date' => '2026-11-20', 'port_name' => 'Port',
            'status' => 'submitted', 'ord_status' => false, 'created_by' => 1,
        ]);

        $this->actAs('super-admin')->postJson('/restore', ['type' => 'order', 'id' => $order])->assertOk()->assertJson(['url' => route('get.all.order')]);

        $this->assertTrue((bool) DB::table('orders')->where('id', $order)->value('ord_status'));
        $this->assertTrue((bool) DB::table('categories')->where('id', $category)->value('status'), 'its category comes back with it, as for items');
    }

    public function test_pd60_ship_users_still_cannot_use_the_trash(): void
    {
        $id = $this->retiredCategory();

        $this->trashDelete('category', $id, 'master')->assertForbidden();
        $this->assertNotNull(DB::table('categories')->find($id));
    }

    public function test_pd60_every_declared_foreign_key_to_a_trash_table_is_checked(): void
    {
        $missing = [];
        foreach (Schema::getTables() as $table) {
            foreach (Schema::getForeignKeys($table['name']) as $key) {
                $target = $key['foreign_table'];
                if (! array_key_exists($target, TrashGuard::REFERENCES)) {
                    continue;
                }
                if (! in_array([$table['name'], $key['columns'][0]], TrashGuard::REFERENCES[$target], true)) {
                    $missing[] = "{$table['name']}.{$key['columns'][0]} -> {$target}";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    private function vesselSurvey(int $vesselId, ?int $surveyId = null): int
    {
        $surveyId ??= DB::table('surveys')->insertGetId(['name' => 'Special', 'created_by' => 'seed', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('vessel_surveys')->insertGetId([
            'vessel_id' => $vesselId, 'survey_id' => $surveyId, 'society_name' => 'Class', 'survey_date' => '2026-01-01',
            'survey_exp_date' => '2027-01-01', 'status' => false, 'created_by' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------- PD-61

    private function attachment(int $userId): int
    {
        return DB::table('attachments')->insertGetId([
            'uploaded_by' => $userId, 'original_filename' => 'quote.pdf', 'path' => 'attachments/quote.pdf', 'mime_type' => 'application/pdf',
            'title' => 'Quote', 'kind' => 'pdf', 'file_size' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function order(): int
    {
        return DB::table('orders')->insertGetId([
            'vessel_id' => self::VESSEL, 'category_id' => DB::table('categories')->value('id') ?? $this->retiredCategory('Req'), 'req_no' => 'REQ/'.uniqid(), 'req_date' => '2026-11-20',
            'port_name' => 'Port', 'status' => 'submitted', 'created_by' => $this->users['chief-officer'], 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // -------------------------------------------- PD-34, cloud side

    private function pmsComponent(int $vesselId = self::VESSEL): string
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('pms_components')->insert([
            'id' => $id, 'vessel_id' => $vesselId,
            'location_id' => DB::table('pms_locations')->insertGetId(['name' => 'ER '.uniqid(), 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]),
            'category_id' => DB::table('pms_component_categories')->insertGetId(['name' => 'Cat '.uniqid(), 'created_at' => now(), 'updated_at' => now()]),
            'condition_id' => (int) DB::table('pms_conditions')->value('id') ?: DB::table('pms_conditions')->insertGetId(['name' => 'Good', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]),
            'name' => 'Pump '.uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function formPart(int $orderId, string $part): int
    {
        return DB::table('order_form_parts')->insertGetId(['order_id' => $orderId, 'part' => $part, 'answers' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<string, array{0: class-string, 1: mixed}> one vessel-owned record of each kind, as model and key */
    private function vesselOwnedRecords(): array
    {
        $order = $this->order();
        $item = $this->item($this->retiredCategory('Own '.uniqid()), true);
        DB::table('vessel_items')->insert(['vessel_id' => self::VESSEL, 'item_id' => $item, 'stock_qty' => 3, 'created_at' => now(), 'updated_at' => now()]);

        return [
            'PMS component (operational)' => [\App\PmsComponent::class, $this->pmsComponent()],
            'equipment (operational)' => [\App\VesselEquipment::class, DB::table('vessel_equipment')->insertGetId(['vessel_id' => self::VESSEL, 'name' => 'Boiler', 'status' => true, 'created_at' => now(), 'updated_at' => now()])],
            'stock link (operational)' => [\App\VesselItem::class, (int) DB::table('vessel_items')->where('item_id', $item)->value('id')],
            'requisition (raised on board)' => [\App\Order::class, $order],
            'form Part A (kept by the vessel)' => [\App\OrderFormPart::class, $this->formPart($order, 'A')],
            'file uploaded on board' => [\App\Attachment::class, $this->attachment($this->users['chief-officer'])],
        ];
    }

    private function exists(string $model, mixed $key): bool
    {
        $instance = new $model;

        return DB::table($instance->getTable())->where($instance->getKeyName(), $key)->whereNull(in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true) ? 'deleted_at' : DB::raw('NULL'))->exists();
    }

    /** Your decision: the office cannot delete an offline vessel's own records through any screen. */
    public function test_pd34_the_office_cannot_delete_an_offline_vessels_own_records_through_the_screens(): void
    {
        $equipment = DB::table('vessel_equipment')->insertGetId(['vessel_id' => self::VESSEL, 'name' => 'Boiler', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        $component = $this->pmsComponent();
        $order = $this->order();
        DB::table('orders')->where('id', $order)->update(['ord_status' => false]);
        $file = $this->attachment($this->users['chief-officer']);
        $this->offline(self::VESSEL);

        foreach (['super-admin', 'am-ssm'] as $role) {
            $responses = [
                'equipment' => $this->actAs($role)->postJson('/vessel/equipment/delete', ['id' => $equipment, 'vessel_id' => self::VESSEL]),
                'PMS component' => $this->actAs($role)->postJson('/pms/components/'.$component.'/delete'),
                'trash: requisition' => $this->actAs($role)->postJson('/permanent-delete', ['type' => 'order', 'id' => $order]),
                'file' => $this->actAs($role)->postJson('/attachments/'.$file.'/delete'),
                'draft line' => $this->actAs($role)->postJson('/requisition/'.$order.'/items/1/remove'),
            ];
            foreach ($responses as $what => $response) {
                $this->assertContains($response->status(), [302, 403, 422], "{$role}: {$what} is refused");
            }
        }

        $this->assertTrue((bool) DB::table('vessel_equipment')->where('id', $equipment)->value('status'));
        $this->assertNull(DB::table('pms_components')->where('id', $component)->value('deleted_at'));
        $this->assertNotNull(DB::table('orders')->find($order));
        $this->assertNull(DB::table('attachments')->where('id', $file)->value('hidden_at'));
    }

    public function test_pd34_any_model_delete_of_an_offline_vessels_own_record_is_refused_on_the_cloud(): void
    {
        $records = $this->vesselOwnedRecords();
        $this->offline(self::VESSEL);

        foreach ($records as $what => [$model, $key]) {
            try {
                $model::query()->findOrFail($key)->delete();
                $this->fail("{$what}: the delete should be refused");
            } catch (\App\Sync\Exceptions\VesselRecordProtected $e) {
                $this->assertStringContainsString('own onboard server', $e->getMessage(), $what);
            }
            $this->assertTrue($this->exists($model, $key), "{$what} is kept");
        }
    }

    public function test_pd34_the_cloud_guard_leaves_every_other_delete_as_it_was(): void
    {
        // A vessel without its own server: the cloud is its only copy.
        // Children first: a requisition's delete cascades to its form parts.
        foreach (array_reverse($this->vesselOwnedRecords()) as $what => [$model, $key]) {
            $model::query()->findOrFail($key)->delete();
            $this->assertFalse($this->exists($model, $key), "{$what}: deleted as before");
        }

        $this->offline(self::VESSEL);
        // Rows the office keeps, and records that are not the vessel's own.
        $order = $this->order();
        $partB = $this->formPart($order, 'B');
        \App\OrderFormPart::findOrFail($partB)->delete();
        $this->assertNull(DB::table('order_form_parts')->find($partB), "the office's Part B");
        $officeFile = $this->attachment($this->users['super-admin']);
        \App\Attachment::findOrFail($officeFile)->delete();
        $this->assertNull(DB::table('attachments')->find($officeFile), 'a file uploaded in the office');
        $group = DB::table('budget_groups')->insertGetId(['name' => 'Spare group', 'status' => true, 'created_by' => 'seed', 'created_at' => now(), 'updated_at' => now()]);
        \App\BudgetGroup::findOrFail($group)->delete();
        $this->assertNull(DB::table('budget_groups')->find($group), 'shared master data');

        // The vessel's own server, and the existing switch.
        $component = $this->pmsComponent();
        config(['navisuite.cloud.lock_offline_vessels' => false]);
        \App\PmsComponent::findOrFail($component)->delete();
        $this->assertNotNull(DB::table('pms_components')->where('id', $component)->value('deleted_at'), 'switch off: as before');
        config(['navisuite.cloud.lock_offline_vessels' => true, 'navisuite.mode' => 'vessel', 'navisuite.vessel_id' => self::VESSEL]);
        $other = $this->pmsComponent();
        \App\PmsComponent::findOrFail($other)->delete();
        $this->assertNotNull(DB::table('pms_components')->where('id', $other)->value('deleted_at'), 'not the cloud: as before');
    }

    public function test_pd34_a_refused_delete_answers_403_with_its_reason(): void
    {
        $component = $this->pmsComponent();
        $this->offline(self::VESSEL);
        \Illuminate\Support\Facades\Route::middleware('web')->post('/_pd34-probe/{id}', fn (string $id) => \App\PmsComponent::findOrFail($id)->delete());

        $this->actAs('super-admin')->postJson('/_pd34-probe/'.$component)
            ->assertForbidden()->assertJsonFragment(['message' => app(\App\Sync\VesselOwnership::class)->cloudDeleteRefusal('pms_components', ['id' => $component])]);
        $this->assertNull(DB::table('pms_components')->where('id', $component)->value('deleted_at'));
    }

    public function test_pd34_the_cfm_import_cannot_remove_an_offline_vessels_components_on_the_cloud(): void
    {
        $this->offline(self::VESSEL);

        $this->artisan('pms:import-cfm', ['code' => 'ZZZ', 'vessel_id' => self::VESSEL, '--remove-unmatched' => true])
            ->expectsOutputToContain('cannot be removed on the cloud')->assertFailed();
        // Without the option, the import goes on as before (here, to the missing export file).
        $this->artisan('pms:import-cfm', ['code' => 'ZZZ', 'vessel_id' => self::VESSEL])
            ->expectsOutputToContain('export file not found')->assertFailed();
    }

    // --------------------------------------------- PD-34, edits

    /** The modules a vessel with its own server synchronises (SYNC_TABLES). */
    private function syncModules(): void
    {
        config(['sync.enabled_tables' => 'currencies,ranks,categories,@master,@inventory,@pms,@requisitions']);
    }

    private function onBoard(): void
    {
        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => self::VESSEL]);
    }

    /** Runs $change and returns the refusal's message, or null when it was saved. */
    private function refusal(callable $change): ?string
    {
        try {
            $change();
        } catch (\App\Sync\Exceptions\WrongSideEdit $e) {
            return $e->getMessage();
        }

        return null;
    }

    private function save(string $model, mixed $key, array $values): void
    {
        $record = $model::findOrFail($key);
        foreach ($values as $column => $value) {
            $record->{$column} = $value;
        }
        $record->save();
    }

    private function stockLink(): int
    {
        $item = $this->item($this->retiredCategory('Stock '.uniqid()), true);

        return DB::table('vessel_items')->insertGetId(['vessel_id' => self::VESSEL, 'item_id' => $item, 'stock_qty' => 3, 'min_qty' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function pmsJob(string $status): string
    {
        $component = $this->pmsComponent();
        $rank = (int) DB::table('ranks')->value('id') ?: DB::table('ranks')->insertGetId(['code' => 'CE', 'name' => 'Chief Engineer', 'created_at' => now(), 'updated_at' => now()]);
        $type = (int) DB::table('pms_job_types')->value('id') ?: DB::table('pms_job_types')->insertGetId(['name' => 'Overhaul', 'abbreviation' => 'OH', 'created_at' => now(), 'updated_at' => now()]);
        $definition = (string) \Illuminate\Support\Str::uuid();
        DB::table('pms_job_definitions')->insert(['id' => $definition, 'vessel_id' => self::VESSEL, 'component_id' => $component, 'name' => 'Overhaul', 'job_type_id' => $type, 'rank_id' => $rank, 'created_at' => now(), 'updated_at' => now()]);
        $job = (string) \Illuminate\Support\Str::uuid();
        DB::table('pms_jobs')->insert(['id' => $job, 'vessel_id' => self::VESSEL, 'component_id' => $component, 'job_definition_id' => $definition, 'status' => $status, 'due_date' => '2026-12-01', 'created_at' => now(), 'updated_at' => now()]);

        return $job;
    }

    public function test_pd34_edits_the_office_cannot_change_an_offline_vessels_own_data_on_the_cloud(): void
    {
        $this->syncModules();
        $order = $this->order();
        $partA = $this->formPart($order, 'A');
        $link = $this->stockLink();
        $component = $this->pmsComponent();
        $plannedJob = $this->pmsJob('Planned');
        $this->offline(self::VESSEL);
        $this->actAs('super-admin');

        $refused = [
            'a PMS component (the vessel\'s table)' => fn () => $this->save(\App\PmsComponent::class, $component, ['name' => 'Office pump']),
            'a stock figure (the vessel\'s column)' => fn () => $this->save(\App\VesselItem::class, $link, ['min_qty' => 9]),
            'a requisition\'s own details (the vessel\'s column)' => fn () => $this->save(\App\Order::class, $order, ['port_name' => 'Office port']),
            'form Part A (the vessel\'s row)' => fn () => $this->save(\App\OrderFormPart::class, $partA, ['answers' => ['x' => 1]]),
            'a PMS job the office does not approve' => fn () => $this->save(\App\PmsJob::class, $plannedJob, ['due_date' => '2027-01-01']),
            'a new PMS component' => function () {
                $new = new \App\PmsComponent;
                foreach ((array) DB::table('pms_components')->first() as $column => $value) {
                    $new->{$column} = $column === 'id' ? (string) \Illuminate\Support\Str::uuid() : $value;
                }
                $new->save();
            },
        ];
        foreach ($refused as $what => $change) {
            $message = $this->refusal($change);
            $this->assertNotNull($message, $what.' is refused');
            $this->assertStringContainsString('own onboard server, so it can only be changed there', $message, $what);
        }

        $this->assertSame('Port', DB::table('orders')->where('id', $order)->value('port_name'));
        $this->assertSame(1, (int) DB::table('vessel_items')->where('id', $link)->value('min_qty'));
        $this->assertSame('{}', DB::table('order_form_parts')->where('id', $partA)->value('answers'));
        $this->assertStringStartsWith('Pump ', DB::table('pms_components')->where('id', $component)->value('name'));
        $this->assertSame(2, DB::table('pms_components')->count(), 'no component added (the other is the job\'s)');
    }

    public function test_pd34_edits_the_office_keeps_its_own_part_of_an_offline_vessels_records(): void
    {
        $this->syncModules();
        $order = $this->order();
        $partB = $this->formPart($order, 'B');
        $approval = DB::table('order_approvals')->insertGetId(['order_id' => $order, 'created_at' => now(), 'updated_at' => now()]);
        $job = $this->pmsJob('WaitingApproval');
        $this->offline(self::VESSEL);
        $this->actAs('am-ssm');

        $this->save(\App\Order::class, $order, ['status' => 'approved by am (ssm)', 'deliver_date' => '2026-11-30']);   // shared and office columns
        $this->save(\App\OrderFormPart::class, $partB, ['answers' => ['office' => 1]]);                                // the office's row
        $this->save(\App\OrderApproval::class, $approval, ['am_app_ssm' => $this->users['am-ssm']]);                  // the office's signature
        app(\App\Services\PmsJobScheduler::class)->approve(\App\PmsJob::findOrFail($job), $this->users['super-admin']);   // the guarded approval

        $this->assertSame('2026-11-30', substr((string) DB::table('orders')->where('id', $order)->value('deliver_date'), 0, 10));
        $this->assertSame(['office' => 1], json_decode(DB::table('order_form_parts')->where('id', $partB)->value('answers'), true));
        $this->assertSame($this->users['am-ssm'], (int) DB::table('order_approvals')->where('id', $approval)->value('am_app_ssm'));
        $this->assertSame('Completed', DB::table('pms_jobs')->where('id', $job)->value('status'));
    }

    public function test_pd34_edits_on_the_cloud_nothing_changes_for_other_vessels_unsynced_tables_or_with_the_switch_off(): void
    {
        $this->syncModules();
        $link = $this->stockLink();
        $component = $this->pmsComponent();
        $this->actAs('super-admin');

        $this->save(\App\VesselItem::class, $link, ['min_qty' => 2]);                       // vessel without its own server
        $this->offline(self::VESSEL);
        config(['navisuite.cloud.lock_offline_vessels' => false]);
        $this->save(\App\VesselItem::class, $link, ['min_qty' => 3]);                       // the switch off
        config(['navisuite.cloud.lock_offline_vessels' => true, 'sync.enabled_tables' => 'currencies,ranks,categories']);
        $this->save(\App\PmsComponent::class, $component, ['name' => 'Not synced']);        // the module not synchronised

        $this->assertSame(3, (int) DB::table('vessel_items')->where('id', $link)->value('min_qty'));
        $this->assertSame('Not synced', DB::table('pms_components')->where('id', $component)->value('name'));
    }

    public function test_pd34_edits_office_data_cannot_be_changed_on_board(): void
    {
        $this->syncModules();
        $order = $this->order();
        $partB = $this->formPart($order, 'B');
        $component = $this->pmsComponent();
        $category = (int) DB::table('categories')->value('id');
        $this->onBoard();
        $this->actAs('master');

        $refused = [
            'a requisition category (the office\'s table)' => fn () => $this->save(\App\Category::class, $category, ['name' => 'Ship name']),
            'a user\'s details (the office\'s columns)' => fn () => $this->save(User::class, $this->users['master'], ['name' => 'Captain']),
            'a new user (only the office creates users)' => function () {
                $user = new User;
                $user->name = 'New';
                $user->email = 'new@test.test';
                $user->password = 'x';
                $user->save();
            },
            'the delivery date (the office\'s column)' => fn () => $this->save(\App\Order::class, $order, ['deliver_date' => '2026-12-01']),
            'form Part B (the office\'s row)' => fn () => $this->save(\App\OrderFormPart::class, $partB, ['answers' => ['x' => 1]]),
            'the component Inactive switch (office only, PD-73)' => fn () => $this->save(\App\PmsComponent::class, $component, ['is_inactive' => true]),
        ];
        foreach ($refused as $what => $change) {
            $message = $this->refusal($change);
            $this->assertNotNull($message, $what.' is refused');
            $this->assertStringContainsString('maintained by the office', $message, $what);
        }
        $this->assertSame('master', DB::table('users')->where('id', $this->users['master'])->value('name'));
        $this->assertSame(0, DB::table('users')->where('email', 'new@test.test')->count());
        $this->assertNull(DB::table('orders')->where('id', $order)->value('deliver_date'));

        // The vessel's own changes, and the shared ones, go on as before.
        $this->save(\App\Order::class, $order, ['port_name' => 'Chittagong', 'status' => 'approved by master']);
        $this->save(\App\PmsComponent::class, $component, ['name' => 'Fire pump']);
        $this->save(User::class, $this->users['master'], ['password' => Hash::make('new secret')]);   // shared: crew change their own password
        $this->save(User::class, $this->users['master'], ['remember_token' => 'local']);              // never travels
        $this->assertSame('Chittagong', DB::table('orders')->where('id', $order)->value('port_name'));
        $this->assertSame('Fire pump', DB::table('pms_components')->where('id', $component)->value('name'));
    }

    public function test_pd34_edits_the_sync_engine_and_migrations_still_write_on_board(): void
    {
        $this->syncModules();
        $category = (int) DB::table('categories')->value('id');
        $this->onBoard();

        \App\Sync\SyncContext::asOrigin((string) \Illuminate\Support\Str::uuid(), fn () => $this->save(\App\Category::class, $category, ['name' => 'From the office']));
        \App\Sync\WrongSideEdits::migrationsStarted();
        try {
            $this->save(\App\Category::class, $category, ['name' => 'By a migration']);
        } finally {
            \App\Sync\WrongSideEdits::migrationsEnded();
        }

        $this->assertSame('By a migration', DB::table('categories')->where('id', $category)->value('name'));
        $this->assertNotNull($this->refusal(fn () => $this->save(\App\Category::class, $category, ['name' => 'After'])), 'the rule is back after the migration');
    }

    public function test_pd34_edits_a_refused_edit_answers_403_with_its_reason_and_stores_no_file(): void
    {
        $this->syncModules();
        $link = $this->stockLink();
        $this->offline(self::VESSEL);
        \Illuminate\Support\Facades\Route::middleware('web')->post('/_pd34-edit-probe/{id}', fn (string $id) => $this->save(\App\VesselItem::class, (int) $id, ['min_qty' => 9]));

        $this->actAs('super-admin')->postJson('/_pd34-edit-probe/'.$link)
            ->assertForbidden()->assertJsonFragment(['message' => app(\App\Sync\WrongSideEdits::class)->refusalFor(tap(\App\VesselItem::findOrFail($link), fn ($m) => $m->min_qty = 9))]);
        $this->assertSame(1, (int) DB::table('vessel_items')->where('id', $link)->value('min_qty'));

        // On board, a crew member's photo and signature are the office's user record.
        $this->onBoard();
        $before = glob($this->publicRoot.'/images/*/*') ?: [];
        $this->actAs('master')->post('/change/file', ['signature' => UploadedFile::fake()->image('sign.png')])->assertForbidden();
        $this->assertNull(DB::table('users')->where('id', $this->users['master'])->value('sign'));
        $this->assertSame($before, glob($this->publicRoot.'/images/*/*') ?: [], 'no file stored');
    }

    public function test_pd34_edits_bulk_writes_of_an_offline_vessels_stock_and_components_are_refused_on_the_cloud(): void
    {
        $this->syncModules();
        $link = $this->stockLink();
        $item = (int) DB::table('vessel_items')->where('id', $link)->value('item_id');
        $this->offline(self::VESSEL);

        $this->assertNotNull($this->refusal(fn () => app(\App\Services\StockService::class)->bulkSet(self::VESSEL, [['item_id' => $item, 'min_qty' => 9]])));
        $this->assertNotNull($this->refusal(fn () => app(\App\Services\StockService::class)->backfillMovementsForVessel(self::VESSEL)));
        $this->assertSame(1, (int) DB::table('vessel_items')->where('id', $link)->value('min_qty'));

        $this->artisan('pms:import-cfm', ['code' => 'ZZZ', 'vessel_id' => self::VESSEL])
            ->expectsOutputToContain('imported there, not on the cloud')->assertFailed();
    }

    public function test_pd34_edits_the_parts_cleanup_leaves_an_offline_vessels_parts_alone_on_the_cloud(): void
    {
        $this->syncModules();
        $order = $this->order();
        DB::table('orders')->where('id', $order)->update(['status' => 'rejected']);
        $job = $this->pmsJob('Planned');
        $part = DB::table('pms_required_parts')->insertGetId(['job_id' => $job, 'part_name' => 'Seal', 'quantity' => 1, 'unit' => 'pc', 'order_id' => $order, 'created_at' => now(), 'updated_at' => now()]);
        $this->offline(self::VESSEL);

        $this->assertSame(0, app(\App\Services\PmsPartsService::class)->releaseStaleLinks());
        $this->assertSame($order, (int) DB::table('pms_required_parts')->where('id', $part)->value('order_id'), 'the vessel releases it on board');

        DB::table('vessels')->where('id', self::VESSEL)->update(['offline_enabled_at' => null]);
        $this->assertSame(1, app(\App\Services\PmsPartsService::class)->releaseStaleLinks(), 'other vessels: as before');
        $this->assertNull(DB::table('pms_required_parts')->where('id', $part)->value('order_id'));
    }

    /** 'refused' when the ownership guard stops $write, 'allowed' when it lets it through; nothing is kept either way. */
    private function verdict(callable $write): string
    {
        DB::beginTransaction();
        try {
            $write();

            return 'allowed';
        } catch (\App\Sync\Exceptions\WrongSideEdit|\App\Sync\Exceptions\VesselRecordProtected) {
            return 'refused';
        } catch (\Illuminate\Database\QueryException) {
            return 'allowed';   // past the guard; the database had its own objection to the test row
        } finally {
            DB::rollBack();
        }
    }

    /** One record of each kind a vessel keeps, for vessel 7. @return array<string, int|string> */
    private function ownerGroupRecords(): array
    {
        $order = $this->order();
        $line = DB::table('order_items')->insertGetId(['order_id' => $order, 'item_id' => $this->item($this->retiredCategory('Line '.uniqid()), true), 'item_qty' => 2, 'created_at' => now(), 'updated_at' => now()]);
        $sr = DB::table('service_requisitions')->insertGetId(['vessel_id' => self::VESSEL, 'req_no' => 'SR/'.uniqid(), 'req_date' => '2026-11-20', 'service_type' => 'shore_repair', 'description' => '-', 'status' => 'submitted', 'created_by' => $this->users['master'], 'created_by_role' => 'master', 'created_at' => now(), 'updated_at' => now()]);
        $job = $this->pmsJob('WaitingApproval');

        return [
            'order' => $order, 'line' => $line, 'sr' => $sr, 'job' => $job,
            'definition' => (string) DB::table('pms_jobs')->where('id', $job)->value('job_definition_id'),
            'component' => (string) DB::table('pms_jobs')->where('id', $job)->value('component_id'),
            'partA' => $this->formPart($order, 'A'), 'partB' => $this->formPart($order, 'B'),
            'approval' => DB::table('order_approvals')->insertGetId(['order_id' => $order, 'created_at' => now(), 'updated_at' => now()]),
            'srItem' => DB::table('service_requisition_items')->insertGetId(['service_requisition_id' => $sr, 'title' => 'Repair', 'created_at' => now(), 'updated_at' => now()]),
            'srApproval' => DB::table('service_requisition_approvals')->insertGetId(['service_requisition_id' => $sr, 'created_at' => now(), 'updated_at' => now()]),
            'step' => DB::table('order_procurement_steps')->insertGetId(['order_id' => $order, 'step' => 'quotation', 'completed_by' => $this->users['am-ssm'], 'created_at' => now(), 'updated_at' => now()]),
            'srStep' => DB::table('service_requisition_procurement_steps')->insertGetId(['service_requisition_id' => $sr, 'step' => 'quotation', 'completed_by' => $this->users['am-ssm'], 'created_at' => now(), 'updated_at' => now()]),
            'consumption' => DB::table('stock_consumptions')->insertGetId(['vessel_id' => self::VESSEL, 'item_id' => DB::table('order_items')->where('id', $line)->value('item_id'), 'consumption_type' => 'general', 'qty' => 1, 'consumed_on' => '2026-11-20', 'purpose' => '-', 'recorded_by' => $this->users['master'], 'created_at' => now(), 'updated_at' => now()]),
            'link' => $this->stockLink(),
        ];
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>}> a new row for each synchronised table: [table, values] */
    private function newRows(array $r): array
    {
        $v = ['vessel_id' => self::VESSEL];

        return [
            'currencies' => ['currencies', ['name' => 'Euro', 'code' => 'EUR']], 'ranks' => ['ranks', ['code' => 'XX', 'name' => 'X']],
            'categories' => ['categories', ['name' => 'X']], 'vessels' => ['vessels', ['name' => 'X']], 'roles' => ['roles', $v + ['role' => 'master']],
            'users' => ['users', ['name' => 'X', 'email' => 'x@x.test']], 'pms_locations' => ['pms_locations', ['name' => 'X']],
            'pms_component_categories' => ['pms_component_categories', ['name' => 'X']], 'pms_job_types' => ['pms_job_types', ['name' => 'X']],
            'pms_conditions' => ['pms_conditions', ['name' => 'X']], 'certificates' => ['certificates', ['name' => 'X']], 'surveys' => ['surveys', ['name' => 'X']],
            'manufacturers' => ['manufacturers', ['name' => 'X']], 'budget_groups' => ['budget_groups', ['name' => 'X']],
            'certificate_categories' => ['certificate_categories', ['name' => 'X']], 'item_groups' => ['item_groups', ['name' => 'X']], 'items' => ['items', ['name' => 'X']],
            'vessel_items' => ['vessel_items', $v + ['item_id' => 1]], 'stock_movements' => ['stock_movements', $v], 'stock_consumptions' => ['stock_consumptions', $v],
            'stock_imports' => ['stock_imports', $v], 'vessel_equipment' => ['vessel_equipment', $v + ['name' => 'X']],
            'pms_components' => ['pms_components', $v + ['name' => 'X']], 'pms_counter_readings' => ['pms_counter_readings', ['component_id' => $r['component']]],
            'pms_job_definitions' => ['pms_job_definitions', $v + ['name' => 'X']], 'pms_job_intervals' => ['pms_job_intervals', ['job_definition_id' => $r['definition']]],
            'pms_job_checklist_items' => ['pms_job_checklist_items', ['job_definition_id' => $r['definition']]], 'pms_jobs' => ['pms_jobs', $v],
            'pms_required_parts' => ['pms_required_parts', ['job_id' => $r['job']]], 'pms_used_parts' => ['pms_used_parts', ['job_id' => $r['job']]],
            'orders' => ['orders', $v], 'order_items' => ['order_items', ['order_id' => $r['order']]], 'order_approvals' => ['order_approvals', ['order_id' => $r['order']]],
            'order_form_parts A' => ['order_form_parts', ['order_id' => $r['order'], 'part' => 'A']], 'order_form_parts B' => ['order_form_parts', ['order_id' => $r['order'], 'part' => 'B']],
            'order_form_part_edits' => ['order_form_part_edits', ['order_form_part_id' => $r['partA']]],
            'order_item_quantity_changes' => ['order_item_quantity_changes', ['order_item_id' => $r['line']]],
            'order_procurement_steps receipt' => ['order_procurement_steps', ['order_id' => $r['order'], 'step' => 'receipt_verification']],
            'order_procurement_steps other' => ['order_procurement_steps', ['order_id' => $r['order'], 'step' => 'delivery']],
            'order_invoices' => ['order_invoices', ['order_id' => $r['order']]],
            'service_requisitions' => ['service_requisitions', $v], 'service_requisition_items' => ['service_requisition_items', ['service_requisition_id' => $r['sr']]],
            'service_requisition_approvals' => ['service_requisition_approvals', ['service_requisition_id' => $r['sr']]],
            'service_requisition_procurement_steps receipt' => ['service_requisition_procurement_steps', ['service_requisition_id' => $r['sr'], 'step' => 'receipt_verification']],
            'service_requisition_procurement_steps other' => ['service_requisition_procurement_steps', ['service_requisition_id' => $r['sr'], 'step' => 'delivery']],
            'service_requisition_invoices' => ['service_requisition_invoices', ['service_requisition_id' => $r['sr']]],
            'vessel_certificates' => ['vessel_certificates', $v], 'attachments' => ['attachments', ['uploaded_by' => $this->users['master']]],
            'attachment_order_item' => ['attachment_order_item', ['order_item_id' => $r['line']]],
            'attachment_stock_consumption' => ['attachment_stock_consumption', ['stock_consumption_id' => $r['consumption']]],
            'attachment_order_step' => ['attachment_order_step', ['order_procurement_step_id' => $r['step']]],
            'attachment_service_req_step' => ['attachment_service_req_step', ['step_id' => $r['srStep']]],
        ];
    }

    /** Office data: refused as a new row on board. The rest (the vessel's, and shared lists) is created on board. */
    private const OFFICE_ROWS = ['currencies', 'ranks', 'categories', 'vessels', 'roles', 'users', 'pms_locations', 'pms_component_categories',
        'pms_job_types', 'pms_conditions', 'certificates', 'surveys', 'order_form_parts B', 'order_item_quantity_changes',
        'order_procurement_steps other', 'order_invoices', 'service_requisition_procurement_steps other', 'service_requisition_invoices',
        // PD-22: a document link on an office procurement stage (the fixtures' stages are office stages).
        'attachment_order_step', 'attachment_service_req_step'];

    /** The vessel's data: refused as a new row on the cloud for a vessel that runs its own server. */
    private const VESSEL_ROWS = ['stock_movements', 'stock_consumptions', 'stock_imports', 'vessel_equipment', 'pms_components', 'pms_counter_readings',
        'pms_job_definitions', 'pms_job_intervals', 'pms_job_checklist_items', 'pms_jobs', 'pms_required_parts', 'pms_used_parts', 'orders',
        'order_items', 'order_approvals', 'order_form_parts A', 'order_form_part_edits', 'order_procurement_steps receipt', 'service_requisitions',
        'service_requisition_items', 'service_requisition_approvals', 'service_requisition_procurement_steps receipt', 'attachment_order_item',
        'attachment_stock_consumption'];

    /** @return array<string, array{0: string, 1: string, 2: array<string, mixed>}> column changes: [table, key, values] */
    private function columnChanges(array $r): array
    {
        return [
            'orders: delivery date (office)' => ['orders', $r['order'], ['deliver_date' => '2026-12-01']],
            'orders: official remarks (office)' => ['orders', $r['order'], ['official_remarks' => 'X']],
            'orders: port (vessel)' => ['orders', $r['order'], ['port_name' => 'X']],
            'orders: status (shared)' => ['orders', $r['order'], ['status' => 'approved by master']],
            'order_items: delivered qty (office)' => ['order_items', $r['line'], ['del_item_qty' => 1]],
            'order_items: received qty (vessel)' => ['order_items', $r['line'], ['rcv_item_qty' => 1]],
            'order_items: requested qty (shared)' => ['order_items', $r['line'], ['item_qty' => 3]],
            'order_approvals: office signature' => ['order_approvals', $r['approval'], ['am_app_ssm' => $this->users['am-ssm']]],
            'order_approvals: master signature' => ['order_approvals', $r['approval'], ['master_app' => $this->users['master']]],
            'order_form_parts: Part A' => ['order_form_parts', $r['partA'], ['answers' => '{"x":1}']],
            'order_form_parts: Part B' => ['order_form_parts', $r['partB'], ['answers' => '{"x":1}']],
            'service_requisition_items: price (office)' => ['service_requisition_items', $r['srItem'], ['unit_price' => 5]],
            'service_requisition_items: title (vessel)' => ['service_requisition_items', $r['srItem'], ['title' => 'X']],
            'service_requisition_approvals: office signature' => ['service_requisition_approvals', $r['srApproval'], ['gm_app' => $this->users['am-ssm']]],
            'service_requisition_approvals: master signature' => ['service_requisition_approvals', $r['srApproval'], ['master_app' => $this->users['master']]],
            'pms_jobs: remark (vessel)' => ['pms_jobs', $r['job'], ['remark' => 'X']],
            'pms_jobs: approval (office, guarded)' => ['pms_jobs', $r['job'], ['status' => 'Completed', 'approved_by' => $this->users['am-ssm'], 'approved_at' => now()]],
            'pms_jobs: cancelled (vessel)' => ['pms_jobs', $r['job'], ['status' => 'Cancelled']],
            'pms_components: name (vessel)' => ['pms_components', $r['component'], ['name' => 'X']],
            'pms_components: Inactive switch (office only)' => ['pms_components', $r['component'], ['is_inactive' => true]],
            'vessel_items: minimum (vessel)' => ['vessel_items', $r['link'], ['min_qty' => 9]],
            'users: name (office)' => ['users', $this->users['master'], ['name' => 'X']],
            'users: signature (office)' => ['users', $this->users['master'], ['sign' => 'x.png']],
            'users: password (shared)' => ['users', $this->users['master'], ['password' => 'x']],
            'users: remember token (local)' => ['users', $this->users['master'], ['remember_token' => 'x']],
            'categories: name (office)' => ['categories', (int) DB::table('categories')->value('id'), ['name' => 'X']],
            'budget_groups: name (ADR-003)' => ['budget_groups', DB::table('budget_groups')->insertGetId(['name' => 'BG '.uniqid(), 'created_by' => $this->users['master'], 'created_at' => now(), 'updated_at' => now()]), ['name' => 'X']],
        ];
    }

    private const REFUSED_ON_BOARD = ['orders: delivery date (office)', 'orders: official remarks (office)', 'order_items: delivered qty (office)',
        'order_approvals: office signature', 'order_form_parts: Part B', 'service_requisition_items: price (office)',
        'service_requisition_approvals: office signature', 'pms_jobs: approval (office, guarded)', 'pms_components: Inactive switch (office only)',
        'users: name (office)', 'users: signature (office)', 'categories: name (office)'];

    private const REFUSED_ON_THE_CLOUD = ['orders: port (vessel)', 'order_items: received qty (vessel)', 'order_approvals: master signature',
        'order_form_parts: Part A', 'service_requisition_items: title (vessel)', 'service_requisition_approvals: master signature',
        'pms_jobs: remark (vessel)', 'pms_jobs: cancelled (vessel)', 'pms_components: name (vessel)', 'pms_components: Inactive switch (office only)',
        'vessel_items: minimum (vessel)'];

    public function test_pd34_edits_every_owner_group_on_board_through_the_query_builder(): void
    {
        $this->syncModules();
        config(['sync.enabled_tables' => config('sync.enabled_tables').',@attachments']);
        $records = $this->ownerGroupRecords();
        $this->onBoard();
        $this->actAs('master');

        foreach ($this->newRows($records) as $case => [$table, $values]) {
            $expected = in_array($case, self::OFFICE_ROWS, true) ? 'refused' : 'allowed';
            $this->assertSame($expected, $this->verdict(fn () => DB::table($table)->insert($values + ['created_at' => now(), 'updated_at' => now()])), "new row on board: {$case}");
        }
        foreach ($this->columnChanges($records) as $case => [$table, $key, $values]) {
            $expected = in_array($case, self::REFUSED_ON_BOARD, true) ? 'refused' : 'allowed';
            $this->assertSame($expected, $this->verdict(fn () => DB::table($table)->where('id', $key)->update($values)), "change on board: {$case}");
        }
        // A mass update reaching an office row is refused as a whole; nothing is written.
        $this->assertSame('refused', $this->verdict(fn () => DB::table('order_form_parts')->where('order_id', $records['order'])->update(['answers' => '{}'])));
    }

    public function test_pd34_edits_every_owner_group_on_the_cloud_for_a_vessel_with_its_own_server(): void
    {
        $this->syncModules();
        config(['sync.enabled_tables' => config('sync.enabled_tables').',@attachments']);
        $records = $this->ownerGroupRecords();
        $this->offline(self::VESSEL);
        $this->actAs('super-admin');

        foreach ($this->newRows($records) as $case => [$table, $values]) {
            $expected = in_array($case, self::VESSEL_ROWS, true) ? 'refused' : 'allowed';
            $this->assertSame($expected, $this->verdict(fn () => DB::table($table)->insert($values + ['created_at' => now(), 'updated_at' => now()])), "new row on the cloud: {$case}");
        }
        foreach ($this->columnChanges($records) as $case => [$table, $key, $values]) {
            $expected = in_array($case, self::REFUSED_ON_THE_CLOUD, true) ? 'refused' : 'allowed';
            $this->assertSame($expected, $this->verdict(fn () => DB::table($table)->where('id', $key)->update($values)), "change on the cloud: {$case}");
        }
        // Mass updates and increments go through the same check.
        $this->assertSame('refused', $this->verdict(fn () => DB::table('vessel_items')->where('vessel_id', self::VESSEL)->increment('min_qty')));
        $this->assertSame('refused', $this->verdict(fn () => \App\Order::where('vessel_id', self::VESSEL)->update(['port_name' => 'X'])));
        $this->assertSame('allowed', $this->verdict(fn () => \App\Order::where('vessel_id', self::VESSEL)->update(['status' => 'approved by am (ssm)'])));
    }

    public function test_pd34_edits_vessels_without_their_own_server_are_unaffected_in_every_group(): void
    {
        $this->syncModules();
        config(['sync.enabled_tables' => config('sync.enabled_tables').',@attachments']);
        $records = $this->ownerGroupRecords();
        $this->actAs('super-admin');   // vessel 7 does not run its own server

        foreach ($this->newRows($records) as $case => [$table, $values]) {
            $this->assertSame('allowed', $this->verdict(fn () => DB::table($table)->insert($values + ['created_at' => now(), 'updated_at' => now()])), "new row: {$case}");
        }
        foreach ($this->columnChanges($records) as $case => [$table, $key, $values]) {
            $this->assertSame('allowed', $this->verdict(fn () => DB::table($table)->where('id', $key)->update($values)), "change: {$case}");
        }
        $this->assertSame('allowed', $this->verdict(fn () => DB::table('orders')->where('id', $records['order'])->delete()));
        $this->assertSame('allowed', $this->verdict(fn () => $this->save(\App\VesselItem::class, $records['link'], ['min_qty' => 5])));
    }

    public function test_pd34_query_builder_deletes_of_an_offline_vessels_own_records_are_refused_on_the_cloud(): void
    {
        $this->syncModules();
        $records = $this->ownerGroupRecords();
        $this->offline(self::VESSEL);

        $this->assertSame('refused', $this->verdict(fn () => DB::table('orders')->where('id', $records['order'])->delete()));
        $this->assertSame('refused', $this->verdict(fn () => DB::table('vessel_items')->delete($records['link'])));
        $this->assertSame('refused', $this->verdict(fn () => DB::table('order_form_parts')->where('order_id', $records['order'])->delete()), 'Part A is among them');
        $this->assertSame('allowed', $this->verdict(fn () => DB::table('order_form_parts')->where('id', $records['partB'])->delete()), 'Part B is the office\'s');
        $this->assertSame('refused', $this->verdict(fn () => \App\PmsComponent::whereKey($records['component'])->forceDelete()));
    }

    public function test_pd34_edits_the_lists_vessels_maintain_are_edited_on_board_through_the_screens(): void
    {
        $this->syncModules();
        $this->onBoard();

        // ADR-003: budget groups and certificate categories are maintained on board too.
        $this->actAs('master')->postJson('/budget-group/store', ['name' => 'Deck stores'])->assertOk();
        $group = (int) DB::table('budget_groups')->where('name', 'Deck stores')->value('id');
        $this->actAs('master')->postJson('/budget-group/update', ['BudgetGroup_Id' => $group, 'name' => 'Deck and cabin stores'])->assertOk();
        $this->actAs('master')->postJson('/certificate-category/store', ['name' => 'Radio'])->assertOk();
        $category = (int) DB::table('certificate_categories')->where('name', 'Radio')->value('id');
        $this->actAs('master')->postJson('/certificate-category/update', ['category_id' => $category, 'name' => 'Radio and GMDSS'])->assertOk();
        // The crew's own password (shared).
        $this->actAs('master')->post('/change/password', ['current_password' => 'secret', 'new_password' => 'new secret', 'new_password_confirmation' => 'new secret'])->assertRedirect();

        $this->assertSame('Deck and cabin stores', DB::table('budget_groups')->where('id', $group)->value('name'));
        $this->assertSame('Radio and GMDSS', DB::table('certificate_categories')->where('id', $category)->value('name'));
        $this->assertTrue(Hash::check('new secret', DB::table('users')->where('id', $this->users['master'])->value('password')));
    }

    public function test_pd34_edits_the_profile_upload_is_shown_disabled_on_board_only(): void
    {
        $this->syncModules();
        $this->actAs('master')->get('/profile')->assertOk()->assertDontSee('profile-files-note');

        $this->onBoard();
        $this->actAs('master')->get('/profile')->assertOk()
            ->assertSee('id="profile-files-note"', false)->assertSee('Please ask the office to change them')->assertSee('disabled data-office-only="1"', false);

        // A form post (not JSON) gets the refusal page with the reason.
        $this->actAs('master')->post('/change/file', ['photo' => UploadedFile::fake()->image('me.png')])
            ->assertForbidden()->assertSee('maintained by the office');
    }

    public function test_pd34_no_application_code_steps_outside_the_ownership_guard(): void
    {
        $calls = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && $file->getPathname() !== app_path('Sync/WrongSideEdits.php')
                && str_contains(file_get_contents($file->getPathname()), 'outsideTheApplication')) {
                $calls[] = $file->getPathname();
            }
        }
        $this->assertSame([], $calls, 'only tests may stand in for a change made outside the application');
    }

    /** The route is reachable by nobody today: ship users by ADR-006, office users by the 'member' group. */
    public function test_pd61_nobody_reaches_the_file_delete_through_the_screens_today(): void
    {
        $id = $this->attachment($this->users['chief-officer']);

        $this->actAs('chief-officer')->postJson("/attachments/{$id}/delete")->assertForbidden();
        $this->actAs('am-ssm')->post("/attachments/{$id}/delete")->assertRedirect('/home');
        $this->assertNull(DB::table('attachments')->where('id', $id)->value('hidden_at'));
    }

    public function test_pd61_a_file_that_is_a_record_of_something_is_kept(): void
    {
        $this->withoutMiddleware([MemberMiddleWare::class, RefuseVesselUserDeletes::class]);
        $uploader = $this->users['am-ssm'];
        $order = $this->order();

        $onStage = $this->attachment($uploader);
        $step = DB::table('order_procurement_steps')->insertGetId(['order_id' => $order, 'step' => 'quotation', 'completed_by' => $uploader, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('attachment_order_step')->insert(['order_procurement_step_id' => $step, 'attachment_id' => $onStage, 'created_at' => now(), 'updated_at' => now()]);

        $onLine = $this->attachment($uploader);
        $line = DB::table('order_items')->insertGetId(['order_id' => $order, 'item_id' => $this->item($this->retiredCategory('L'), true), 'item_qty' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('attachment_order_item')->insert(['order_item_id' => $line, 'attachment_id' => $onLine, 'created_at' => now(), 'updated_at' => now()]);

        foreach ([$onStage => 'completed procurement stage', $onLine => 'requisition item'] as $id => $why) {
            $this->actAs('am-ssm')->postJson("/attachments/{$id}/delete")->assertStatus(422)->assertJsonFragment(['message' => \App\Attachment::find($id)->deletionRefusal()]);
            $this->assertStringContainsString($why, \App\Attachment::find($id)->deletionRefusal());
            $this->assertNull(DB::table('attachments')->where('id', $id)->value('hidden_at'));
        }
        $this->assertSame(1, DB::table('attachment_order_step')->count());
        $this->assertSame(1, DB::table('attachment_order_item')->count());
    }

    public function test_pd61_a_file_on_a_consumption_record_is_kept(): void
    {
        $id = $this->attachment($this->users['am-ssm']);
        $consumption = DB::table('stock_consumptions')->insertGetId([
            'vessel_id' => self::VESSEL, 'item_id' => $this->item($this->retiredCategory('C'), true), 'qty' => 1,
            'consumption_type' => array_key_first(\App\StockConsumption::TYPES), 'consumed_on' => '2026-11-20', 'purpose' => 'Test', 'recorded_by' => $this->users['master'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('attachment_stock_consumption')->insert(['stock_consumption_id' => $consumption, 'attachment_id' => $id, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertStringContainsString('consumption record', \App\Attachment::find($id)->deletionRefusal());
    }

    public function test_pd61_any_other_delete_hides_the_file_and_keeps_it(): void
    {
        $this->withoutMiddleware([MemberMiddleWare::class, RefuseVesselUserDeletes::class]);
        $id = $this->attachment($this->users['am-ssm']);

        $this->actAs('am-ssm')->postJson("/attachments/{$id}/delete")->assertOk();

        $row = DB::table('attachments')->find($id);
        $this->assertNotNull($row, 'the record is kept');
        $this->assertNotNull($row->hidden_at);
        $this->assertSame($this->users['am-ssm'], (int) $row->hidden_by);
        $this->assertSame([], \App\Attachment::visible()->where('uploaded_by', $this->users['am-ssm'])->pluck('id')->all(), 'no longer offered in the library');
    }

    // ------------------------------------------------------------- PD-62

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n% ".$name."\n");
    }

    private function certificatePayload(array $extra = []): array
    {
        $category = DB::table('certificate_categories')->value('id')
            ?? DB::table('certificate_categories')->insertGetId(['name' => 'PD-62 category', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $extra + ['Vessel_Name' => self::VESSEL, 'category_id' => $category, 'title' => 'IOPP Certificate', 'Issue_Date' => '2026-01-01', 'is_permanent' => 1];
    }

    public function test_pd62_every_certificate_copy_is_kept_and_listed(): void
    {
        $created = $this->actAs('super-admin')->post('/certificate/store', $this->certificatePayload(['Certificate_Copy' => $this->pdf('first.pdf')]))->assertOk()->json();
        $id = (int) $created[1]['id'];

        foreach (['second.pdf', 'third.pdf'] as $name) {
            $this->actAs('master')->post('/certificate/update', $this->certificatePayload(['Cert_Id' => $id, 'Certificate_Copy' => $this->pdf($name)]))->assertOk();
        }

        $copies = DB::table('vessel_certificate_copies')->where('vessel_certificate_id', $id)->orderBy('id')->get();
        $this->assertCount(3, $copies);
        $this->assertSame(DB::table('vessel_certificates')->where('id', $id)->value('cert_copy'), $copies->last()->path, 'the newest is the current copy');
        $this->assertSame([null, null, null], $copies->pluck('replaced_at')->all(), 'a copy record is never changed afterwards');
        $this->assertSame([$this->users['super-admin'], $this->users['master'], $this->users['master']], $copies->map(fn ($c) => (int) $c->uploaded_by)->all());
        foreach ($copies as $copy) {
            $this->assertFileExists($this->publicRoot.'/'.$copy->path, 'every version is still on disk');
            $this->assertSame(hash_file('sha256', $this->publicRoot.'/'.$copy->path), $copy->sha256, 'its checksum is recorded');
            $this->assertSame(filesize($this->publicRoot.'/'.$copy->path), (int) $copy->file_size);
        }

        $shown = $this->actAs('super-admin')->getJson("/certificate/{$id}")->assertOk()->json(2);
        $this->assertCount(3, $shown);
        $this->assertSame([true, false, false], array_column($shown, 'current'), 'newest first; only the certificate\'s own copy is current');
    }

    public function test_pd62_a_copy_uploaded_before_the_history_began_is_kept_when_replaced(): void
    {
        file_put_contents($this->publicRoot.'/images/cert_copy/old.pdf', '%PDF-1.4 old');
        $id = DB::table('vessel_certificates')->insertGetId([
            'vessel_id' => self::VESSEL, 'certificate_id' => 0, 'title' => 'Registry', 'issue_date' => '2025-01-01',
            'cert_copy' => 'images/cert_copy/old.pdf', 'status' => true, 'created_by' => $this->users['super-admin'], 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actAs('super-admin')->post('/certificate/update', $this->certificatePayload(['Cert_Id' => $id, 'Certificate_Copy' => $this->pdf('new.pdf')]))->assertOk();

        $copies = DB::table('vessel_certificate_copies')->where('vessel_certificate_id', $id)->orderBy('id')->get();
        $this->assertSame(['images/cert_copy/old.pdf', null, null], [$copies[0]->path, $copies[0]->uploaded_by, $copies[0]->uploaded_at]);
        $this->assertSame(hash('sha256', '%PDF-1.4 old'), $copies[0]->sha256, 'its checksum is taken when it is first listed');
        $this->assertCount(2, $copies);
        $this->assertFileExists($this->publicRoot.'/images/cert_copy/old.pdf', 'no longer deleted on replacement');
    }

    public function test_pd62_a_certificate_with_copy_history_stays_in_the_trash(): void
    {
        $created = $this->actAs('super-admin')->post('/certificate/store', $this->certificatePayload(['Certificate_Copy' => $this->pdf('a.pdf')]))->json();
        $id = (int) $created[1]['id'];
        DB::table('vessel_certificates')->where('id', $id)->update(['status' => false]);

        $this->trashDelete('certificate', $id)->assertStatus(422);
        $this->assertNotNull(DB::table('vessel_certificates')->find($id));
    }
}
