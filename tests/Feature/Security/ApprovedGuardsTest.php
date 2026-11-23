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
