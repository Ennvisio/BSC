<?php

namespace Tests\Feature\ServiceRequisition;

use App\ServiceProcurementStage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/**
 * The Vessel / Type / date filter above the service requisition lists -
 * ServiceRequisitionApprovalController::listFilters().
 */
class ServiceRequisitionFilterTest extends TestCase
{
    use BuildsRequisitionSchema;

    private int $otherVesselId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();

        Artisan::call('migrate', [
            '--path' => glob(database_path('migrations/*_service_requisition*.php')),
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->otherVesselId = $this->makeVessel('MV OTHER SHIP')->id;
        $raiser = $this->makeUser('chief-officer', $this->vesselId);

        foreach ([
            ['SR-ALPHA-001', $this->vesselId, 'shore_repair', '2026-03-10'],
            ['SR-BRAVO-002', $this->otherVesselId, 'renewal', '2026-05-20'],
            ['SR-CHARLIE-003', $this->vesselId, 'renewal', '2026-07-01'],
        ] as [$no, $vessel, $type, $date]) {
            $id = DB::table('service_requisitions')->insertGetId([
                'vessel_id' => $vessel, 'req_no' => $no, 'req_date' => $date,
                'service_type' => $type, 'description' => 'x', 'status' => 'approved by gm-srd',
                'is_submitted' => true, 'created_by' => $raiser->id, 'created_by_role' => 'chief-officer',
                'procurement_stage' => ServiceProcurementStage::CLOSED,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('service_requisition_approvals')->insert(['service_requisition_id' => $id]);
        }
    }

    private function listAs(string $role, string $url, array $query = [])
    {
        return $this->actingAs($this->makeUser($role))->get($url.($query ? '?'.http_build_query($query) : ''))->assertOk();
    }

    public function test_no_filter_lists_everything_with_the_filter_panel(): void
    {
        $this->listAs('super-admin', '/service-requisitions')
            ->assertSee('id="service_search_form"', false)
            ->assertSee('All vessels')->assertSee('All types')
            ->assertSee('SR-ALPHA-001')->assertSee('SR-BRAVO-002')->assertSee('SR-CHARLIE-003');
    }

    public function test_filters_by_vessel(): void
    {
        $this->listAs('super-admin', '/service-requisitions', ['ship_id' => $this->otherVesselId])
            ->assertSee('SR-BRAVO-002')->assertDontSee('SR-ALPHA-001')->assertDontSee('SR-CHARLIE-003')
            ->assertSee('requisition found');
    }

    public function test_filters_by_type(): void
    {
        $this->listAs('super-admin', '/service-requisitions', ['service_type' => 'renewal'])
            ->assertSee('SR-BRAVO-002')->assertSee('SR-CHARLIE-003')->assertDontSee('SR-ALPHA-001');
    }

    public function test_filters_by_date_range_and_single_day(): void
    {
        $this->listAs('super-admin', '/service-requisitions', ['from_date' => '2026-05-01', 'end_date' => '2026-07-31'])
            ->assertSee('SR-BRAVO-002')->assertSee('SR-CHARLIE-003')->assertDontSee('SR-ALPHA-001');

        $this->listAs('super-admin', '/service-requisitions', ['from_date' => '2026-03-10'])
            ->assertSee('SR-ALPHA-001')->assertDontSee('SR-BRAVO-002')->assertDontSee('SR-CHARLIE-003');
    }

    public function test_filters_combine_on_the_paged_approved_list(): void
    {
        $this->listAs('gm-srd', '/service-requisitions/approved', ['ship_id' => $this->vesselId, 'service_type' => 'renewal'])
            ->assertSee('id="service_search_form"', false)
            ->assertSee('SR-CHARLIE-003')->assertDontSee('SR-ALPHA-001')->assertDontSee('SR-BRAVO-002');
    }

    public function test_ship_users_do_not_get_the_filter_or_other_vessels(): void
    {
        $this->actingAs($this->makeUser('master', $this->vesselId))
            ->get('/service-requisitions?ship_id='.$this->otherVesselId)->assertOk()
            ->assertDontSee('id="service_search_form"', false)
            ->assertSee('SR-ALPHA-001')->assertDontSee('SR-BRAVO-002');
    }
}
