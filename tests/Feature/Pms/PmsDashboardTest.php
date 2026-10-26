<?php

namespace Tests\Feature\Pms;

use App\PmsJob;
use App\PmsJobDefinition;
use App\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsPmsSchema;
use Tests\TestCase;

class PmsDashboardTest extends TestCase
{
    use BuildsPmsSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildPmsSchema();
        Carbon::setTestNow('2026-01-10');
        CarbonImmutable::setTestNow('2026-01-10');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function definition(string $name = 'Overhaul'): PmsJobDefinition
    {
        $component = $this->makeComponent(['name' => $name.' COMPONENT']);

        return $this->makeDefinition($component, ['name' => $name]);
    }

    private function job(PmsJobDefinition $definition, array $attributes): PmsJob
    {
        return PmsJob::create($attributes + [
            'vessel_id' => $definition->vessel_id,
            'component_id' => $definition->component_id,
            'job_definition_id' => $definition->id,
        ]);
    }

    private function userWithRole(string $role, string $userType): User
    {
        $user = User::create(['name' => $role, 'email' => $role.'@test.local', 'password' => 'x']);
        DB::table('roles')->insert(['user_id' => $user->id, 'role' => $role, 'user_type' => $userType]);

        return $user;
    }

    /** The number shown on one dashboard tile, read straight out of the rendered page. */
    private function tileValue(string $html, string $label): ?int
    {
        preg_match('/'.preg_quote($label, '/').'<\/span>\s*<span class="pms-dash-tile-value[^"]*">([\d,]+)</', $html, $m);

        return isset($m[1]) ? (int) str_replace(',', '', $m[1]) : null;
    }

    // ---------------------------------------------------------------- the header button

    public function test_pms_button_is_visible_only_with_pms_access(): void
    {
        // The topbar partial on its own, not a full page - the rest of /home
        // pulls in tables (service_requisitions etc.) this minimal PMS schema
        // doesn't build, and the button's visibility is topbar's alone.
        $marker = 'href="'.route('pms.dashboard').'"';

        $shipUser = User::find($this->userId); // chief-officer, seeded by BuildsPmsSchema
        $this->actingAs($shipUser);
        $this->assertStringContainsString($marker, view('partials.topbar')->render());

        $fleetViewer = $this->userWithRole('gm-srd', 'srd');
        $this->actingAs($fleetViewer);
        $this->assertStringContainsString($marker, view('partials.topbar')->render());

        // An SSM role is neither a ship role nor one of PmsAccess's fleet viewers.
        $noAccess = $this->userWithRole('am-ssm', 'ssm');
        $this->actingAs($noAccess);
        $this->assertStringNotContainsString($marker, view('partials.topbar')->render());
    }

    // ---------------------------------------------------------------- scope

    public function test_ship_users_dashboard_shows_only_their_own_vessel(): void
    {
        $this->job($this->definition(), ['status' => PmsJob::PLANNED, 'due_date' => '2026-02-01']);

        $shipUser = User::find($this->userId);
        $response = $this->actingAs($shipUser)->get('/pms');

        $response->assertOk();
        $response->assertSee('MT TEST VESSEL');
        // The fleet-only "Vessel job overview" table never renders for a ship user.
        $response->assertDontSee('Vessel job overview');
    }

    public function test_a_shore_user_with_no_vessel_picked_sees_the_fleet_table(): void
    {
        $this->job($this->definition(), ['status' => PmsJob::PLANNED, 'due_date' => '2026-02-01']);

        $shore = $this->userWithRole('gm-srd', 'srd');
        $response = $this->actingAs($shore)->get('/pms');

        $response->assertOk();
        $response->assertSee('Vessel job overview');
        $response->assertSee('All vessels (fleet)', false);
    }

    // ---------------------------------------------------------------- tile counts

    public function test_tile_counts_match_the_jobs_pages_own_filters(): void
    {
        $definition = $this->definition();
        $this->job($definition, ['status' => PmsJob::PLANNED, 'due_date' => '2026-03-01']);
        $this->job($definition, ['status' => PmsJob::PLANNED, 'due_date' => '2026-03-02']);
        $this->job($definition, ['status' => PmsJob::DUE_SOON, 'due_date' => '2026-01-15']);
        $this->job($definition, ['status' => PmsJob::OVERDUE, 'due_date' => '2025-12-01']);
        $this->job($definition, ['status' => PmsJob::OVERDUE, 'due_date' => '2025-12-05']);
        $this->job($definition, ['status' => PmsJob::OVERDUE, 'due_date' => '2025-12-10']);
        $this->job($definition, ['status' => PmsJob::WAITING_APPROVAL]);
        $this->job($definition, ['status' => PmsJob::COMPLETED, 'completed_at' => '2026-01-05']);
        $this->job($definition, ['status' => PmsJob::CANCELLED]);

        $shipUser = User::find($this->userId);
        $html = $this->actingAs($shipUser)->get('/pms')->assertOk()->getContent();

        // Same OPEN_STATUSES / per-status counts the Jobs page's own filter
        // pills show for this vessel (see PmsJobController::rows()).
        $this->assertSame(7, $this->tileValue($html, 'Open jobs'), 'planned + duesoon + overdue + waiting approval');
        $this->assertSame(3, $this->tileValue($html, 'Overdue'));
        $this->assertSame(1, $this->tileValue($html, 'Due soon'));

        $this->assertSame(
            PmsJob::where('vessel_id', $this->vesselId)->where('status', PmsJob::OVERDUE)->count(),
            $this->tileValue($html, 'Overdue')
        );
        $this->assertSame(
            PmsJob::where('vessel_id', $this->vesselId)->whereIn('status', PmsJob::OPEN_STATUSES)->count(),
            $this->tileValue($html, 'Open jobs')
        );
    }

    // ---------------------------------------------------------------- overdue-by-age chart

    public function test_overdue_by_age_bucket_counts(): void
    {
        $definition = $this->definition();
        // 0-30 days late, 31-90, 91-365, over 365 - one each.
        $this->job($definition, ['status' => PmsJob::OVERDUE, 'due_date' => '2025-12-31']); // 10 days
        $this->job($definition, ['status' => PmsJob::OVERDUE, 'due_date' => '2025-11-26']); // 45 days
        $this->job($definition, ['status' => PmsJob::OVERDUE, 'due_date' => '2025-06-24']); // 200 days
        $this->job($definition, ['status' => PmsJob::OVERDUE, 'due_date' => '2024-12-06']); // 400 days
        // Not counted: overdue with no due date (RH-only) can't be dated, and a Planned job isn't overdue at all.
        $this->job($definition, ['status' => PmsJob::OVERDUE, 'due_date' => null]);
        $this->job($definition, ['status' => PmsJob::PLANNED, 'due_date' => '2026-02-01']);

        $shipUser = User::find($this->userId);
        $html = $this->actingAs($shipUser)->get('/pms')->assertOk()->getContent();

        foreach (['0–30 days', '31–90 days', '91–365 days', 'Over 365 days'] as $bucket) {
            $this->assertStringContainsString('1 overdue '.$bucket.' on MT TEST VESSEL', $html, $bucket);
        }

        preg_match('/pms-chart-hrow-total">(\d+)</', $html, $m);
        $this->assertSame('4', $m[1] ?? null, 'the undated and the planned job are both left out of the total');
    }
}
