<?php

namespace Tests\Feature\Sync\Modules;

use App\PmsComponent;
use App\PmsCounterReading;
use App\PmsJob;
use App\PmsJobDefinition;
use App\Services\PmsJobScheduler;
use App\Sync\Models\SyncConflict;
use Illuminate\Support\Facades\DB;

/**
 * The PMS is kept on board: components, readings, job plans and completed
 * work go up with their original dates and authors. The office's one change
 * is approving a completed job, which comes back down.
 */
class PmsSyncTest extends BusinessSyncTestCase
{
    /** A main engine with a counter and a yearly overhaul that needs office approval; returns the open job's id. */
    private function plannedOnBoard(): string
    {
        return $this->onShip(function () {
            $component = PmsComponent::create([
                'vessel_id' => 7, 'location_id' => DB::table('pms_locations')->value('id'), 'name' => 'MAIN ENGINE',
                'category_id' => DB::table('pms_component_categories')->value('id'),
                'condition_id' => DB::table('pms_conditions')->where('is_default', true)->value('id'), 'has_counter' => true,
            ]);
            $reading = new PmsCounterReading(['component_id' => $component->id, 'reading_date' => '2026-11-01', 'value' => 12500, 'entered_by' => $this->userId('ce@mvtest.test')]);
            $reading->created_at = '2026-11-01 08:15:00';
            $reading->updated_at = '2026-11-01 08:15:00';
            $reading->save();

            $definition = PmsJobDefinition::create([
                'vessel_id' => 7, 'component_id' => $component->id, 'name' => 'Overhaul', 'requires_approval' => true,
                'job_type_id' => DB::table('pms_job_types')->value('id'), 'rank_id' => DB::table('ranks')->value('id'),
                'start_date' => '2026-01-01',
            ]);
            $definition->intervals()->create(['every' => 12, 'period' => 'Month']);
            $definition->checklistItems()->create(['position' => 1, 'text' => 'Check injectors']);

            return app(PmsJobScheduler::class)->definitionSaved($definition->fresh())->id;
        });
    }

    private function completeOnBoard(string $jobId): void
    {
        $this->onShip(function () use ($jobId) {
            $job = PmsJob::findOrFail($jobId);
            $this->as('ce@mvtest.test')->postJson("/pms/jobs/{$jobId}/complete", [
                'completed_at' => '2026-11-15', 'done_by_rank_id' => DB::table('ranks')->value('id'),
                'condition_after_id' => DB::table('pms_conditions')->where('name', 'Good')->value('id'),
                'remark' => 'Injectors replaced', 'checklist' => $job->definition->checklistItems()->pluck('id')->all(),
            ])->assertOk();
        });
    }

    public function test_work_done_on_board_reaches_the_office_with_its_original_dates_and_authors(): void
    {
        $jobId = $this->plannedOnBoard();
        $this->completeOnBoard($jobId);

        $this->assertCycleOk();

        $this->onCloud(function () use ($jobId) {
            $job = DB::table('pms_jobs')->where('id', $jobId)->first();
            $this->assertNotNull($job);
            $this->assertSame(PmsJob::WAITING_APPROVAL, $job->status);
            $this->assertStringStartsWith('2026-11-15', $job->completed_at);
            $this->assertSame('Injectors replaced', $job->remark);
            $this->assertSame($this->userId('ce@mvtest.test'), (int) $job->completed_by, 'the chief engineer signed it, in the office too');

            $reading = DB::table('pms_counter_readings')->sole();
            $this->assertSame(['2026-11-01 08:15:00', $this->userId('ce@mvtest.test')], [$reading->created_at, (int) $reading->entered_by], 'when and by whom the reading was entered');

            $this->assertSame(2, DB::table('pms_jobs')->where('job_definition_id', $job->job_definition_id)->count(), 'the next job planned on board came too');
            $this->assertSame(1, DB::table('pms_job_checklist_items')->count());
            $this->assertSame('MAIN ENGINE', DB::table('pms_components')->value('name'));
        });
        $onBoard = $this->onShip(fn () => DB::table('pms_jobs')->where('id', $jobId)->first());
        $inOffice = $this->onCloud(fn () => DB::table('pms_jobs')->where('id', $jobId)->first());
        foreach (['completed_at', 'created_at', 'updated_at', 'due_date', 'rh_at_completion'] as $column) {
            $this->assertSame($onBoard->{$column}, $inOffice->{$column}, $column);
        }
    }

    public function test_the_offices_approval_comes_back_to_the_vessel(): void
    {
        $jobId = $this->plannedOnBoard();
        $this->completeOnBoard($jobId);
        $this->assertCycleOk();

        $this->onCloud(fn () => $this->as('gm.srd@office.test')->postJson("/pms/jobs/{$jobId}/approve")->assertOk());
        $this->assertCycleOk();

        $this->onShip(function () use ($jobId) {
            $job = DB::table('pms_jobs')->where('id', $jobId)->first();
            $this->assertSame(PmsJob::COMPLETED, $job->status);
            $this->assertSame($this->userId('gm.srd@office.test'), (int) $job->approved_by);
            $this->assertNotNull($job->approved_at);
            $this->assertSame('Injectors replaced', $job->remark);
        });
        $this->assertSame(0, $this->onCloud(fn () => SyncConflict::query()->count()));
    }

    public function test_the_office_cannot_change_anything_else_on_a_job(): void
    {
        $jobId = $this->plannedOnBoard();
        $this->assertCycleOk();

        // Changed in the office database directly, bypassing the screens.
        $this->onCloud(fn () => \App\Sync\WrongSideEdits::outsideTheApplication(fn () => DB::table('pms_jobs')->where('id', $jobId)->update([
            'status' => PmsJob::CANCELLED, 'remark' => 'office edit', 'due_date' => '2030-01-01', 'updated_at' => now()->addMinute(),
        ])));
        $this->assertCycleOk();

        $job = $this->onShip(fn () => DB::table('pms_jobs')->where('id', $jobId)->first());
        $this->assertNotSame(PmsJob::CANCELLED, $job->status, 'only WaitingApproval to Completed is the office\'s to set');
        $this->assertNull($job->remark);
        $this->assertNotSame('2030-01-01', substr((string) $job->due_date, 0, 10));

        // A job created in the office is refused on board.
        $this->onCloud(fn () => \App\Sync\WrongSideEdits::outsideTheApplication(function () use ($jobId) {
            $row = (array) DB::table('pms_jobs')->where('id', $jobId)->first();
            DB::table('pms_jobs')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'status' => PmsJob::PLANNED, 'sync_version' => 1] + array_diff_key($row, array_flip(['id', 'status', 'sync_version', 'completed_at', 'remark'])));
        }));
        $result = $this->syncCycle();
        $this->assertSame(1, $result['summary']['pulled']['rejected']);
        $this->assertStringContainsString('Only the vessel creates pms_jobs', (string) $this->onShip(fn () => DB::table('sync_inbox')->where('status', 'failed')->value('last_error')));
    }

    public function test_super_admin_cannot_edit_the_pms_of_a_vessel_with_its_own_server_in_the_office(): void
    {
        $this->onCloud(function () {
            $payload = ['name' => 'BOW THRUSTER', 'location_id' => DB::table('pms_locations')->value('id'), 'category_id' => DB::table('pms_component_categories')->value('id')];
            $this->as('admin@office.test')->postJson('/pms/components?vessel_id=7', $payload + ['vessel_id' => 7])->assertForbidden();
            $this->assertNotSame(403, $this->as('admin@office.test')->postJson('/pms/components?vessel_id=8', $payload + ['vessel_id' => 8])->status(), 'other vessels as before');
        });
    }

    public function test_the_offices_nightly_refresh_leaves_offline_vessels_to_their_own_server(): void
    {
        $this->plannedOnBoard();
        $this->assertCycleOk();

        $this->onCloud(fn () => $this->artisan('pms:refresh-jobs')->expectsOutputToContain('Skipped vessels with their own server: 7')->assertSuccessful()->run());
        $this->onCloud(fn () => $this->artisan('pms:refresh-jobs', ['--vessel' => 7])->expectsOutputToContain('refreshed on board')->assertSuccessful()->run());
        $this->onShip(fn () => $this->artisan('pms:refresh-jobs', ['--vessel' => 7])->expectsOutputToContain('PMS jobs refreshed')->assertSuccessful()->run());
    }
}
