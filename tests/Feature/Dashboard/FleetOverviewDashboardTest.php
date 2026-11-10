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
            'SSM' => ['am-ssm', false],
            'SRD delegate' => ['dgm-srd', false],
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

        // Certificates/Surveys pages 403 for anyone but the fleet records
        // admins, so only they get a card that links there.
        $linksRecords
            ? $response->assertSee(url('/home/certificate'), false)
            : $response->assertDontSee('href="'.url('/home/certificate').'"', false);
    }

    public function test_ship_dashboard_does_not_show_it(): void
    {
        $this->actingAs($this->makeUser('master', $this->vesselId))->get('/home')
            ->assertOk()
            ->assertDontSee('Validity of Certificates')
            ->assertDontSee('Survey Report');
    }
}
