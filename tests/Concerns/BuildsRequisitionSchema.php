<?php

namespace Tests\Concerns;

use App\Attachment;
use App\Category;
use App\Item;
use App\Order;
use App\OrderApproval;
use App\OrderItem;
use App\User;
use App\Vessel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds just the tables the requisition/procurement module needs, from the
 * real migration files, in the test suite's in-memory SQLite database - same
 * approach as Tests\Concerns\BuildsPmsSchema, for the same reason (the app's
 * full migration chain mixes in modules these tests don't touch).
 *
 * Every migration here actually runs (nothing is reimplemented by hand): the
 * one that used to be MySQL-only raw SQL (add_wizard_fields_to_orders_table)
 * was made driver-aware instead of worked around, so this list is the real
 * schema, not a stand-in for it.
 */
trait BuildsRequisitionSchema
{
    protected int $vesselId;

    protected function buildRequisitionSchema(): void
    {
        $migrations = [
            '2014_10_12_000000_create_users_table.php',
            '2019_02_13_084750_create_vessels_table.php',
            // Not used by anything requisition-related - only here because
            // partials.sidebar (every admin-master page) queries their counts
            // for the nav badges, for the roles these tests act as.
            '2019_02_11_091238_create_surveys_table.php',
            '2019_04_23_092447_create_vessel_certificates_table.php',
            '2019_02_14_041739_create_categories_table.php',
            '2019_02_14_041843_create_items_table.php',
            '2019_02_14_041855_create_orders_table.php',
            '2019_02_14_041907_create_order_items_table.php',
            '2019_02_26_103228_create_roles_table.php',
            '2019_04_24_070601_create_order_approvals_table.php',
            '2026_08_26_184701_create_item_groups_table.php',
            '2026_08_26_184702_add_catalog_fields_to_items_table.php',
            '2026_08_26_184703_create_vessel_items_table.php',
            '2026_08_26_184704_create_item_imports_table.php',
            '2026_08_28_162629_add_account_number_to_items_table.php',
            '2026_09_04_200600_add_is_catalog_to_categories_table.php',
            '2026_09_04_200601_add_category_id_to_item_groups_table.php',
            '2026_09_04_200601_add_category_id_to_item_imports_table.php',
            '2026_09_05_062949_create_ports_table.php',
            '2026_09_05_075947_create_budget_groups_table.php',
            '2026_09_05_075948_add_wizard_fields_to_orders_table.php',
            '2026_09_05_120104_formalize_undocumented_order_approval_columns.php',
            '2026_09_05_123645_add_phase1_workflow_columns_to_order_approvals.php',
            '2026_09_05_154337_add_reason_to_orders_table.php',
            '2026_09_05_183959_add_assigned_to_ssm_to_order_approvals.php',
            '2026_09_06_060000_add_stock_to_vessel_items_table.php',
            '2026_09_06_060100_add_stock_snapshot_to_order_items_table.php',
            '2026_09_09_120000_drop_remarks_from_orders_table.php',
            '2026_09_13_090000_create_attachments_table.php',
            '2026_09_13_090100_create_attachment_order_item_table.php',
            '2026_09_14_090000_add_assigned_to_srd_to_order_approvals.php',
            '2026_09_14_100000_create_order_procurement_steps_table.php',
            '2026_09_14_100100_create_order_invoices_table.php',
            '2026_09_14_100200_create_attachment_order_step_table.php',
            '2026_09_14_100300_add_procurement_stage_to_orders_table.php',
            '2026_09_14_100400_add_invoice_fields_to_order_items_table.php',
            '2026_09_14_110000_backfill_procurement_stage_for_live_orders.php',
            '2026_09_17_090000_add_rejection_fields_to_orders_table.php',
            '2026_09_22_100000_create_order_form_parts_table.php',
            '2026_10_02_110000_add_official_remarks_to_orders_table.php',
            '2026_10_07_100000_add_machinery_details_to_orders_table.php',
            '2026_10_10_100000_add_edited_by_to_order_form_parts_table.php',
            '2026_10_10_120000_create_order_item_quantity_changes_table.php',
            '2026_10_11_100000_add_subtotal_to_order_invoices_table.php',
        ];

        Artisan::call('migrate', [
            '--path' => array_map(fn ($m) => database_path('migrations/'.$m), $migrations),
            '--realpath' => true,
            '--force' => true,
        ]);

        // The real create_currencies_table migration also adds currency_code
        // to service_requisition_invoices, a different module's table this
        // schema doesn't build - so this is a stand-in for just the part
        // order_invoices actually needs (ProcurementController::saveInvoice()
        // writes currency_code; validateInvoice() checks Currency::isActiveCode()).
        Schema::create('currencies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('code', 3)->unique();
            $table->string('name', 60);
            $table->boolean('status')->default(true);
        });
        DB::table('currencies')->insert(['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'status' => true]);
        Schema::table('order_invoices', function (Blueprint $table) {
            $table->char('currency_code', 3)->default('BDT')->after('invoice_date');
        });

        $this->vesselId = $this->makeVessel('MT TEST VESSEL')->id;
    }

    /** Vessel is $guarded = ['*'] too - inserted via the query builder, same as BuildsPmsSchema does. */
    protected function makeVessel(string $name = 'MT TEST VESSEL'): Vessel
    {
        $id = DB::table('vessels')->insertGetId([
            'name' => $name, 'owner_name' => '-', 'owner_address' => '-', 'manager_name' => '-',
            'manager_address' => '-', 'master_name' => '-', 'master_cert_no' => '-', 'master_cert_validity' => '2030-01-01',
            'ch_eng_name' => '-', 'ch_eng_cert_no' => '-', 'ch_eng_cert_validity' => '2030-01-01', 'status' => true,
        ]);

        return Vessel::find($id);
    }

    /** A user with a role row - user_type defaults from Role::TYPE_BY_ROLE when not given, same as the app does. */
    protected function makeUser(string $role, ?int $vesselId = null, ?string $userType = null, ?string $email = null): User
    {
        $email ??= $role.'-'.uniqid().'@test.local';
        $user = User::create(['name' => $role, 'email' => $email, 'password' => 'x']);

        DB::table('roles')->insert([
            'user_id' => $user->id,
            'vessel_id' => $vesselId,
            'role' => $role,
            'user_type' => $userType ?? (\App\Role::TYPE_BY_ROLE[$role] ?? null),
            'created_by' => 'test',
            'status' => true,
        ]);

        return $user->fresh();
    }

    /**
     * add_is_catalog_to_categories_table already seeds Spares/Stores/
     * Chemicals/Lub Oil (SPR/STR/CHM/LUB) as real migration data - reused
     * here by symbol rather than inserted fresh, same as that migration's
     * own idempotent find-or-create. Any other symbol is created new.
     */
    protected function makeCategory(string $symbol = 'SPR', ?string $name = null): Category
    {
        $existing = Category::where('symbol', $symbol)->first();
        if ($existing) {
            return $existing;
        }

        $id = DB::table('categories')->insertGetId([
            'name' => $name ?? $symbol.' category '.uniqid(),
            'symbol' => $symbol,
            'created_by' => 'test',
            'status' => true,
        ]);

        return Category::find($id);
    }

    protected function makeItem(int $categoryId, array $overrides = []): Item
    {
        $id = DB::table('items')->insertGetId($overrides + [
            'category_id' => $categoryId,
            'impa_code' => random_int(100000, 999999),
            'name' => 'Test item '.uniqid(),
            'unit' => 'pcs',
            'created_by' => 'test',
            'status' => true,
        ]);

        return Item::find($id);
    }

    /**
     * Order/OrderApproval/OrderItem are all $guarded = ['*'] - no mass
     * assignment, so this sets attributes directly. $attrs['approval'] (an
     * array of order_approvals columns => values) is pulled out before the
     * rest goes onto the order itself.
     */
    protected function makeOrder(array $attrs = []): Order
    {
        $approvalAttrs = $attrs['approval'] ?? [];
        unset($attrs['approval']);

        $order = new Order;
        foreach ($attrs + $this->defaultOrderAttrs() as $key => $value) {
            $order->{$key} = $value;
        }
        $order->save();

        $approval = new OrderApproval;
        foreach (['order_id' => $order->id] + $approvalAttrs as $key => $value) {
            $approval->{$key} = $value;
        }
        $approval->save();

        return $order->fresh();
    }

    private function defaultOrderAttrs(): array
    {
        return [
            'vessel_id' => $this->vesselId,
            'category_id' => $this->makeCategory()->id,
            'req_no' => 'REQ/'.uniqid(),
            'req_date' => now()->toDateString(),
            'port_name' => 'Test Port',
            'status' => 'on process',
            'created_by' => 1,
            'created_by_role' => 'chief-officer',
            'ord_status' => true,
        ];
    }

    protected function makeOrderApproval(int $orderId, array $overrides = []): OrderApproval
    {
        $approval = OrderApproval::where('order_id', $orderId)->first() ?? new OrderApproval(['order_id' => $orderId]);
        foreach ($overrides as $key => $value) {
            $approval->{$key} = $value;
        }
        $approval->save();

        return $approval->fresh();
    }

    protected function makeOrderItem(int $orderId, int $itemId, int $qty = 1, array $overrides = []): OrderItem
    {
        $line = new OrderItem;
        foreach ($overrides + ['order_id' => $orderId, 'item_id' => $itemId, 'item_qty' => $qty] as $key => $value) {
            $line->{$key} = $value;
        }
        $line->save();

        return $line->fresh();
    }

    /** Attachment is $guarded = ['*'] too - same insertGetId() pattern as makeVessel()/makeCategory(). */
    protected function makeAttachment(int $uploadedBy, array $overrides = []): Attachment
    {
        $id = DB::table('attachments')->insertGetId($overrides + [
            'uploaded_by' => $uploadedBy,
            'title' => 'Test file '.uniqid(),
            'original_filename' => 'test.pdf',
            'path' => 'attachments/'.$uploadedBy.'/'.uniqid().'.pdf',
            'mime_type' => 'application/pdf',
            'kind' => 'pdf',
            'file_size' => 1024,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Attachment::find($id);
    }
}
