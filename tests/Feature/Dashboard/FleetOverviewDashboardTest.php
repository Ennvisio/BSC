<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/**
 * Fleet cards, Validity of Certificates and Survey Report on every shore
 * SRD/SSM dashboard - partials.fleet-overview via HomeController@index.
 */
class FleetOverviewDashboardTest extends TestCase
{
    use BuildsRequisitionSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();

        // GM (SRD) and its delegates also get service requisition stat cards.
        Artisan::call('migrate', [
            '--path' => glob(database_path('migrations/*_service_requisition*.php')),
            '--realpath' => true,
            '--force' => true,
        ]);

        // The certificate migrations use MySQL-only UPDATE..JOIN backfills,
        // so the tables the overview reads are built by hand under sqlite.
        Schema::dropIfExists('vessel_certificates');
        Schema::create('vessel_certificates', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vessel_id');
            $t->unsignedBigInteger('category_id')->nullable();
            $t->string('title');
            $t->string('issue_auth')->nullable();
            $t->date('issue_date')->nullable();
            $t->date('exp_date')->nullable();
            $t->unsignedInteger('validity_years')->nullable();
            $t->boolean('is_permanent')->default(false);
            $t->string('cert_copy')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
        foreach (['certificates', 'certificate_categories'] as $table) {
            Schema::dropIfExists($table);
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->boolean('status')->default(true);
                $t->timestamps();
            });
        }
        Schema::create('vessel_surveys', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('survey_id');
            $t->unsignedBigInteger('vessel_id');
            $t->date('survey_date')->nullable();
            $t->date('survey_exp_date')->nullable();
            $t->boolean('status')->default(true);
            $t->timestamps();
        });

        DB::table('vessel_certificates')->insert([
            'vessel_id' => $this->vesselId, 'title' => 'Load Line',
            'issue_date' => '2026-01-01', 'exp_date' => '2031-01-01', 'status' => true,
        ]);
    }

    public static function shoreRoles(): array
    {
        return [
            'SSM' => ['am-ssm', true],
            'SRD delegate' => ['dgm-srd', true],
            'GM (SRD)' => ['gm-srd', true],
            'super-admin' => ['super-admin', true],
        ];
    }

    #[DataProvider('shoreRoles')]
    public function test_shore_dashboard_shows_the_fleet_overview(string $role, bool $linksRecords): void
    {
        $response = $this->actingAs($this->makeUser($role))->get('/home');
        $response->assertOk();

        $response->assertSee('Validity of Certificates')
            ->assertSee('Survey Report')
            ->assertSee('Certificates')
            ->assertSee('Load Line');

        $linksRecords
            ? $response->assertSee(url('/home/certificate'), false)
            : $response->assertDontSee('href="'.url('/home/certificate').'"', false);
    }

    public static function srdSsmRoles(): array
    {
        return [['am-ssm'], ['superintendent-ssm'], ['dgm-srd'], ['technical-superintendent'], ['gm-srd']];
    }

    #[DataProvider('srdSsmRoles')]
    public function test_srd_and_ssm_get_fleet_records_and_categories_menu(string $role): void
    {
        $user = $this->makeUser($role);
        $html = $this->actingAs($user)->get('/home')->assertOk()->getContent();

        foreach (['/home/certificate', '/home/survey', '/home/vessel', '/home/category'] as $path) {
            $this->assertSame(1, substr_count($html, 'href="'.url($path).'" class="srd-nav-item'), "$role sidebar: $path");
        }

        // Categories sits under Requisitions, and there's no Stores section.
        $this->assertStringNotContainsString('<div class="srd-nav-label">Stores</div>', $html);
        $requisitions = strpos($html, '<div class="srd-nav-label">Requisitions</div>');
        $this->assertNotFalse($requisitions);
        $this->assertGreaterThan($requisitions, strpos($html, 'href="'.url('/home/category').'" class="srd-nav-item'));

        $this->actingAs($user)->get('/home/certificate')->assertOk()->assertSee('Load Line');
        $this->actingAs($user)->get('/home/survey')->assertOk();
    }

    public function test_super_admin_has_categories_under_requisitions_not_stores(): void
    {
        $html = $this->actingAs($this->makeUser('super-admin'))->get('/home')->assertOk()->getContent();

        $this->assertStringNotContainsString('<div class="srd-nav-label">Stores</div>', $html);
        $requisitions = strpos($html, '<div class="srd-nav-label">Requisitions</div>');
        $this->assertNotFalse($requisitions);
        $this->assertGreaterThan($requisitions, strpos($html, 'href="'.url('/home/category').'" class="srd-nav-item'));
    }

    public function test_super_admin_menu_has_item_and_service_requisitions(): void
    {
        $admin = $this->makeUser('super-admin');
        $html = $this->actingAs($admin)->get('/home')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.url('/home/order').'" class="srd-nav-item ">', $html);
        $this->assertStringContainsString('Item Requisitions</a>', $html);
        $this->assertStringContainsString('href="'.route('service-requisition.index').'" class="srd-nav-item ">', $html);
        $this->assertStringContainsString('Service Requisitions</a>', $html);

        $this->actingAs($admin)->get(route('service-requisition.index'))->assertOk();
    }

    #[DataProvider('adminMenuRoles')]
    public function test_certificate_categories_live_on_their_own_admin_page(string $role): void
    {
        DB::table('certificate_categories')->insert(['name' => 'Statutory Certificates', 'status' => true]);
        $user = $this->makeUser($role);

        $html = $this->actingAs($user)->get('/home')->assertOk()->getContent();
        $admin = strpos($html, '<div class="srd-nav-label">Admin</div>');
        $this->assertNotFalse($admin);
        $this->assertGreaterThan($admin, strpos($html, 'href="'.route('get.certificate-categories').'" class="srd-nav-item'));

        $this->actingAs($user)->get('/home/certificate-category')->assertOk()
            ->assertSee('Certificate Categories')
            ->assertSee('Statutory Certificates');

        // No longer a section on the Certificates page itself.
        $this->actingAs($user)->get('/home/certificate')->assertOk()
            ->assertDontSee('id="cert-category-table"', false);
    }

    public static function adminMenuRoles(): array
    {
        return [['super-admin'], ['gm-srd']];
    }

    public function test_ship_crew_cannot_open_certificate_categories(): void
    {
        $this->actingAs($this->makeUser('master', $this->vesselId))->get('/home/certificate-category')->assertForbidden();
    }

    public function test_ship_crew_still_cannot_open_fleet_certificates(): void
    {
        $this->actingAs($this->makeUser('chief-officer', $this->vesselId))->get('/home/certificate')->assertForbidden();
    }

    public function test_ship_dashboard_does_not_show_it(): void
    {
        $this->actingAs($this->makeUser('master', $this->vesselId))->get('/home')
            ->assertOk()
            ->assertDontSee('Validity of Certificates')
            ->assertDontSee('Survey Report');
    }
}
