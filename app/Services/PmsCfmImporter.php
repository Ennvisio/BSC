<?php

namespace App\Services;

use App\Manufacturer;
use App\PmsComponent;
use App\PmsComponentCategory;
use App\PmsCondition;
use App\PmsCounterReading;
use App\PmsJob;
use App\PmsJobChecklistItem;
use App\PmsJobDefinition;
use App\PmsJobInterval;
use App\PmsJobType;
use App\PmsLocation;
use App\Rank;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Imports one vessel's PMS from a CFM (Cloud Fleet Manager) export into this
 * app's own schema - components, counters, job definitions and open jobs.
 *
 * A CFM export is a flatter, richer model than ours in one place that
 * matters: a "fleet" job definition (VesselID and ComponentGuid both null)
 * stands for every component CFM happened to generate a job against, where
 * our schema ties one definition to exactly one component. Such a
 * definition is split into one definition per component - see
 * importDefinitions(). Everything else is close to a 1:1 field mapping.
 *
 * Every created row the import controls (component, definition, job) is
 * tagged with cfm_guid - the export's own Guid, or, for a definition split
 * out of a fleet one, a deterministic uuid5 of (fleet definition
 * Guid, component Guid). A second run of the same file only ever finds
 * these rows by that guid and adds nothing already there - see the
 * "_already_imported" counters.
 *
 * Imported jobs keep CFM's own due_date / due_running_hours / warning_date
 * exactly as given - this importer never calls PmsJobScheduler::
 * definitionSaved() or recomputes a due date, only PmsJobScheduler::
 * statusFor() to classify Planned/DueSoon/Overdue from them. Once an
 * imported job is completed through the normal screens, the next job is
 * generated the normal way, counting from that completion.
 */
class PmsCfmImporter
{
    /** CFM's job-interval period codes that line up with ours, besides RunningHour. */
    private const CALENDAR_PERIODS = ['Day', 'Week', 'Month', 'Year'];

    /**
     * Fleet job definitions that are test/junk data in the CFM export, not
     * real PMS content - "Irene Flowers" is Lorem-ipsum text with an open
     * job against every single component on the vessel. Always skipped,
     * on top of whatever --exclude-def adds.
     */
    private const ALWAYS_EXCLUDED_DEFINITIONS = ['6bab916b-eec5-4181-a916-bae996010691'];

    public function __construct(private PmsJobScheduler $scheduler)
    {
    }

    /**
     * @param  array  $vesselData  the decoded CFM_export_<CODE>.json
     * @param  array  $master  the decoded CFM_export_master.json's "master" node
     */
    /** @param  array<string>  $excludeDefGuids  extra CFM job definition guids to skip, on top of ALWAYS_EXCLUDED_DEFINITIONS */
    public function import(int $vesselId, array $vesselData, array $master, int $userId, bool $dryRun = false, array $excludeDefGuids = []): PmsCfmImportResult
    {
        $result = new PmsCfmImportResult;
        $lookups = $this->buildLookups($master);
        $excluded = array_fill_keys(array_map('strtolower', array_unique(array_merge(self::ALWAYS_EXCLUDED_DEFINITIONS, $excludeDefGuids))), true);
        $isExcluded = fn (string $guid): bool => isset($excluded[strtolower($guid)]);

        try {
            DB::transaction(function () use ($vesselId, $vesselData, $lookups, $userId, $result, $dryRun, $isExcluded) {
                $componentIds = $this->importComponents($vesselId, $vesselData['components'] ?? [], $lookups, $result);
                $this->importCounters($vesselData['counters'] ?? [], $componentIds, $userId, $result);

                $openJobs = $vesselData['openJobs'] ?? [];
                $jobDefinitions = $vesselData['jobDefinitions'] ?? [];
                $componentGuidsByFleetDef = $this->groupFleetComponentGuids($jobDefinitions, $openJobs);
                $result->fleetDefinitions = $this->fleetDefinitionReport($jobDefinitions, $componentGuidsByFleetDef, $isExcluded);

                [$definitionIds, $definitionMeta] = $this->importDefinitions(
                    $vesselId, $jobDefinitions, $componentIds, $componentGuidsByFleetDef, $lookups, $result, $isExcluded
                );

                $this->importJobs($vesselId, $openJobs, $componentIds, $definitionIds, $definitionMeta, $userId, $result, $isExcluded);

                if ($dryRun) {
                    throw new PmsCfmDryRunRollback;
                }
            });
        } catch (PmsCfmDryRunRollback) {
            // Intentional: every write above is rolled back, counts and warnings stand.
        }

        return $result;
    }

    /** One row per fleet definition - name and how many components it has an open job against - so an admin can review before the real import. */
    private function fleetDefinitionReport(array $jobDefinitions, array $componentGuidsByFleetDef, callable $isExcluded): array
    {
        $report = [];
        foreach ($jobDefinitions as $d) {
            if (empty($d['ComponentGuid'])) {
                $report[] = [
                    'guid' => $d['Guid'],
                    'name' => $d['Name'],
                    'job_count' => count($componentGuidsByFleetDef[$d['Guid']] ?? []),
                    'excluded' => $isExcluded($d['Guid']),
                ];
            }
        }

        return $report;
    }

    // ------------------------------------------------------------------
    // Components
    // ------------------------------------------------------------------

    /** @return array<string,string> CFM component Guid => our component id */
    private function importComponents(int $vesselId, array $components, array $lookups, PmsCfmImportResult $result): array
    {
        $byGuid = [];
        foreach ($components as $c) {
            $byGuid[$c['Guid']] = $c;
        }

        // Real (non-folder) components, parents before children - a folder's
        // own record is never created, but its children attach to whatever
        // real component sits above it (or the root, if there's none).
        $real = array_filter($components, fn ($c) => empty($c['IsFolder']));
        $realParentGuid = [];
        foreach ($real as $c) {
            $realParentGuid[$c['Guid']] = $this->realParentGuid($c['Guid'], $byGuid);
        }
        $depth = [];
        foreach ($realParentGuid as $guid => $_) {
            $this->depthOf($guid, $realParentGuid, $depth);
        }
        usort($real, fn ($a, $b) => $depth[$a['Guid']] <=> $depth[$b['Guid']]);

        $ids = [];
        // (parentId|lower name) => our component id, for the "same name
        // under the same parent already exists" reuse rule - matching the
        // app's own sibling-name uniqueness, which (see the generated
        // unique_scope column) is scoped by parent, not by location.
        $existingByKey = PmsComponent::where('vessel_id', $vesselId)->get(['id', 'name', 'parent_id'])
            ->mapWithKeys(fn ($c) => [$this->componentKey($c->parent_id, $c->name) => $c->id])->all();

        foreach ($real as $c) {
            $guid = $c['Guid'];

            $already = PmsComponent::where('vessel_id', $vesselId)->where('cfm_guid', $guid)->value('id');
            if ($already) {
                $ids[$guid] = $already;
                $result->add('components_already_imported');

                continue;
            }

            $parentOurId = $realParentGuid[$guid] !== null ? ($ids[$realParentGuid[$guid]] ?? null) : null;
            $key = $this->componentKey($parentOurId, $c['Name']);

            if (isset($existingByKey[$key])) {
                $id = $existingByKey[$key];
                PmsComponent::whereKey($id)->whereNull('cfm_guid')->update(['cfm_guid' => $guid]);
                $ids[$guid] = $id;
                $result->add('components_reused');

                continue;
            }

            $component = PmsComponent::create([
                'cfm_guid' => $guid,
                'vessel_id' => $vesselId,
                'location_id' => $this->resolveLocation($c['VesselLocationGuid'] ?? null, $lookups, $result),
                'parent_id' => $parentOurId,
                'name' => trim($c['Name']),
                'category_id' => $this->resolveCategory($c['ComponentCategoryGuid'] ?? null, $c['CategoryName'] ?? null, $lookups, $result),
                'manufacturer_id' => $this->resolveManufacturer($c['PmsManufacturerGuid'] ?? null, $lookups, $result),
                'serial_no' => $this->clean($c['SerialNumber'] ?? null),
                'installation_date' => $this->dateOnly($c['InstallationDate'] ?? null),
                'condition_id' => $this->resolveCondition($c, $lookups),
                'has_counter' => ($c['CounterMode'] ?? 'None') !== 'None',
                'is_critical' => (bool) ($c['IsCritical'] ?? false),
                'is_class_relevant' => (bool) ($c['IsClassRelevant'] ?? false),
                'is_inactive' => (bool) ($c['IsInactive'] ?? false),
                'class_code' => $this->clean($c['ClassReferenceCode'] ?? null),
                'remark' => $this->clean($c['Remark'] ?? null),
                'sort_order' => (int) ($c['SortOrder'] ?? 0),
            ]);

            $ids[$guid] = $component->id;
            $existingByKey[$key] = $component->id;
            $result->add('components_created');
        }

        return $ids;
    }

    /** The nearest ancestor that isn't itself a folder, or null if only folders (or nothing) sit above it. */
    private function realParentGuid(string $guid, array $byGuid, int $guard = 0): ?string
    {
        $parent = $byGuid[$guid]['ParentComponentGuid'] ?? null;
        if ($parent === null || $guard > 50) {
            return $parent;
        }

        return ! empty($byGuid[$parent]['IsFolder'] ?? false) ? $this->realParentGuid($parent, $byGuid, $guard + 1) : $parent;
    }

    private function depthOf(string $guid, array $realParentGuid, array &$memo, int $guard = 0): int
    {
        if (isset($memo[$guid])) {
            return $memo[$guid];
        }
        $parent = $realParentGuid[$guid] ?? null;

        return $memo[$guid] = ($parent === null || $guard > 50) ? 0 : $this->depthOf($parent, $realParentGuid, $memo, $guard + 1) + 1;
    }

    private function componentKey(?string $parentId, string $name): string
    {
        return ($parentId ?? 'null').'|'.$this->norm($name);
    }

    // ------------------------------------------------------------------
    // Counters
    // ------------------------------------------------------------------

    private function importCounters(array $counters, array $componentIds, int $userId, PmsCfmImportResult $result): void
    {
        foreach ($counters as $counter) {
            if ($counter['LastCounterValue'] === null || $counter['LastCounterDate'] === null) {
                continue;
            }

            $componentId = $componentIds[$counter['Guid']] ?? null;
            if (! $componentId) {
                $result->warn('component_missing_for_counter', 'Counter for component "'.($counter['Name'] ?? $counter['Guid']).'" - that component was not imported.');

                continue;
            }

            $date = $this->dateOnly($counter['LastCounterDate']);
            $alreadyCovered = PmsCounterReading::where('component_id', $componentId)->where('reading_date', '>=', $date)->exists();
            if ($alreadyCovered) {
                $result->add('readings_skipped');

                continue;
            }

            PmsCounterReading::create([
                'component_id' => $componentId,
                'reading_date' => $date,
                'value' => round((float) $counter['LastCounterValue'], 1),
                'entered_by' => $userId,
            ]);
            $result->add('readings_created');
        }
    }

    // ------------------------------------------------------------------
    // Job definitions
    // ------------------------------------------------------------------

    /** CFM fleet definition Guid => the distinct component Guids its open jobs actually target. */
    private function groupFleetComponentGuids(array $jobDefinitions, array $openJobs): array
    {
        $fleetGuids = array_flip(array_map(fn ($d) => $d['Guid'], array_filter($jobDefinitions, fn ($d) => empty($d['ComponentGuid']))));
        if (! $fleetGuids) {
            return [];
        }

        $byDef = [];
        foreach ($openJobs as $job) {
            if (isset($fleetGuids[$job['SourceJobDefinitionGuid']])) {
                $byDef[$job['SourceJobDefinitionGuid']][$job['ComponentGuid']] = true;
            }
        }

        return array_map('array_keys', $byDef);
    }

    /**
     * @return array{0: array<string,string>, 1: array<string,array{warning_period:int,warning_unit:string}>}
     *               [0] "defGuid" or "defGuid|componentGuid" => our definition id, matching how importJobs() looks a job's definition up
     *               [1] our definition id => the bits importJobs() needs without re-querying the database
     */
    private function importDefinitions(int $vesselId, array $jobDefinitions, array $componentIds, array $fleetComponentGuids, array $lookups, PmsCfmImportResult $result, callable $isExcluded): array
    {
        $ids = [];
        $meta = [];

        foreach ($jobDefinitions as $d) {
            if ($isExcluded($d['Guid'])) {
                $result->add('definitions_excluded');

                continue;
            }

            if (! empty($d['ComponentGuid'])) {
                $componentId = $componentIds[$d['ComponentGuid']] ?? null;
                if (! $componentId) {
                    $result->warn('component_missing_for_definition', 'Job definition "'.$d['Name'].'" - its component was not imported.');
                    $result->add('definitions_skipped');

                    continue;
                }

                $id = $this->definitionOnce($vesselId, $d, $d['Guid'], $componentId, $lookups, $result, false);
                if ($id) {
                    $ids[$d['Guid']] = $id;
                    $meta[$id] = $this->definitionMeta($d);
                }

                continue;
            }

            // Fleet: one row per component an open job of this definition actually targets.
            $componentGuids = $fleetComponentGuids[$d['Guid']] ?? [];
            if (! $componentGuids) {
                $result->warn('fleet_definition_unused', 'Fleet job definition "'.$d['Name'].'" has no open job referencing it - skipped.');
                $result->add('definitions_skipped');

                continue;
            }

            foreach ($componentGuids as $componentGuid) {
                $componentId = $componentIds[$componentGuid] ?? null;
                if (! $componentId) {
                    $result->warn('component_missing_for_definition', 'Job definition "'.$d['Name'].'" - its component was not imported.');
                    $result->add('definitions_skipped');

                    continue;
                }

                $cfmGuid = Uuid::uuid5(Uuid::NAMESPACE_OID, $d['Guid'].'|'.$componentGuid)->toString();
                $id = $this->definitionOnce($vesselId, $d, $cfmGuid, $componentId, $lookups, $result, true);
                if ($id) {
                    $ids[$d['Guid'].'|'.$componentGuid] = $id;
                    $meta[$id] = $this->definitionMeta($d);
                }
            }
        }

        return [$ids, $meta];
    }

    /** Create one definition row (plus its intervals and checklist) unless cfm_guid already exists. */
    private function definitionOnce(int $vesselId, array $d, string $cfmGuid, string $componentId, array $lookups, PmsCfmImportResult $result, bool $isFleetSplit): ?string
    {
        $already = PmsJobDefinition::where('vessel_id', $vesselId)->where('cfm_guid', $cfmGuid)->value('id');
        if ($already) {
            $result->add('definitions_already_imported');

            return $already;
        }

        $isSingle = (bool) $d['IsSingleJob'];
        $warning = ($d['WarningPeriod'][0] ?? null) ?: ['Number' => 7, 'Unit' => 'Days'];
        $dueType = null;
        $dueDate = null;
        $dueRh = null;
        if ($isSingle) {
            if (! empty($d['DueDate'])) {
                $dueType = 'Date';
                $dueDate = $this->dateOnly($d['DueDate']);
            } elseif ($d['DueRunningHours'] !== null) {
                $dueType = 'RunningHours';
                $dueRh = round((float) $d['DueRunningHours'], 1);
            }
        }

        $definition = PmsJobDefinition::create([
            'cfm_guid' => $cfmGuid,
            'vessel_id' => $vesselId,
            'component_id' => $componentId,
            'name' => trim($d['Name']),
            'job_type_id' => $this->resolveJobType($d['PmsJobTypeGuid'], $lookups, $result),
            'rank_id' => $this->resolveRank($d['RankID'] ?? null, $componentId, $lookups, $result),
            'performed_by' => $this->performedBy($d['JobPerformerTypeCode'] ?? null, $result),
            'perform_when' => $this->performWhen($d['PerformRestrictionCode'] ?? null, $result),
            'is_single' => $isSingle,
            'due_type' => $dueType,
            'due_date' => $dueDate,
            'due_running_hours' => $dueRh,
            'warning_period' => (int) $warning['Number'],
            'warning_unit' => $warning['Unit'],
            'internal_code' => $this->clean($d['InternalCode'] ?? null),
            'class_code' => $this->clean($d['ClassCode'] ?? null),
            'staff_needed' => ($d['RequiredStaffCount'] ?? 0) ?: null,
            'hours_needed' => ($d['RequiredHours'] ?? 0) ?: null,
            'minutes_needed' => ($d['RequiredMinutes'] ?? 0) ?: null,
            'description' => $this->clean($d['Description'] ?? null),
            'requires_approval' => (bool) ($d['RequiresJobDoneApproval'] ?? false),
            'is_critical' => (bool) ($d['IsCritical'] ?? false),
            'is_class_relevant' => (bool) ($d['IsClassRelevant'] ?? false),
            'is_safety_relevant' => (bool) ($d['IsSafetyRelevant'] ?? false),
            'is_active' => empty($d['IsInactive']),
        ]);

        if (! $isSingle) {
            foreach ($d['Intervals'] ?? [] as $interval) {
                $period = $this->intervalPeriod($interval, $result, $d['Name']);
                if ($period) {
                    PmsJobInterval::create(['job_definition_id' => $definition->id, 'every' => (int) $interval['IntervalEvery'], 'period' => $period]);
                }
            }
        }

        foreach ($d['ChecklistItems'] ?? [] as $i => $item) {
            $text = trim((string) ($item['Text'] ?? ''));
            if ($text !== '') {
                PmsJobChecklistItem::create(['job_definition_id' => $definition->id, 'position' => $item['Position'] ?? ($i + 1), 'text' => $text]);
            }
        }

        $result->add('definitions_created');
        if ($isFleetSplit) {
            $result->add('definitions_fleet_split');
        }

        return $definition->id;
    }

    private function definitionMeta(array $d): array
    {
        $warning = ($d['WarningPeriod'][0] ?? null) ?: ['Number' => 7, 'Unit' => 'Days'];

        return ['warning_period' => (int) $warning['Number'], 'warning_unit' => $warning['Unit']];
    }

    private function intervalPeriod(array $interval, PmsCfmImportResult $result, string $defName): ?string
    {
        if (($interval['Type'] ?? null) === 'RunningHoursBased') {
            return 'RunningHour';
        }
        if (($interval['Type'] ?? null) === 'TimeBased' && in_array($interval['IntervalPeriodTypeCode'], self::CALENDAR_PERIODS, true)) {
            return $interval['IntervalPeriodTypeCode'];
        }

        $result->warn('interval_unrecognised', 'Job definition "'.$defName.'" - interval type "'.($interval['Type'] ?? '').'/'.($interval['IntervalPeriodTypeCode'] ?? '').'" not recognised, skipped.');

        return null;
    }

    // ------------------------------------------------------------------
    // Jobs
    // ------------------------------------------------------------------

    private function importJobs(int $vesselId, array $openJobs, array $componentIds, array $definitionIds, array $definitionMeta, int $userId, PmsCfmImportResult $result, callable $isExcluded): void
    {
        $today = CarbonImmutable::today();
        $tomorrow = $today->addDay()->toDateString();
        $currentRh = [];

        foreach ($openJobs as $job) {
            if ($isExcluded($job['SourceJobDefinitionGuid'])) {
                $result->add('jobs_excluded');

                continue;
            }

            $componentId = $componentIds[$job['ComponentGuid']] ?? null;
            if (! $componentId) {
                $result->warn('component_missing_for_job', 'Open job "'.$job['Name'].'" - its component was not imported.');
                $result->add('jobs_skipped');

                continue;
            }

            $fleetKey = $job['SourceJobDefinitionGuid'].'|'.$job['ComponentGuid'];
            $definitionId = $definitionIds[$fleetKey] ?? $definitionIds[$job['SourceJobDefinitionGuid']] ?? null;
            if (! $definitionId) {
                $result->warn('definition_missing_for_job', 'Open job "'.$job['Name'].'" - its job definition was not imported.');
                $result->add('jobs_skipped');

                continue;
            }

            if (! empty($job['LastDoneDate'])) {
                $this->importLastDone($vesselId, $job, $componentId, $definitionId, $result);
            }

            if (PmsJob::where('cfm_guid', $job['Guid'])->exists()) {
                $result->add('jobs_already_imported');

                continue;
            }

            $dueDate = $this->dateOnly($job['RescheduleToDate'] ?? $job['DueDate'] ?? null);
            $warningDate = $this->dateOnly($job['WarningDate'] ?? null);
            $dueRh = $job['DueRunningHours'] !== null ? round((float) $job['DueRunningHours'], 1) : null;
            $rescheduled = ! empty($job['RescheduleToDate']);

            if (! empty($job['CompletionDate']) && empty($job['IsApproved'])) {
                $status = PmsJob::WAITING_APPROVAL;
                $completedAt = $this->dateOnly($job['CompletionDate']);
                $rhAtCompletion = $job['RunningHoursAtCompletion'] !== null ? round((float) $job['RunningHoursAtCompletion'], 1) : null;
            } else {
                $status = $this->scheduler->statusFor(
                    $dueDate ? CarbonImmutable::parse($dueDate) : null,
                    $warningDate ? CarbonImmutable::parse($warningDate) : null,
                    $dueRh,
                    $currentRh[$componentId] ??= $this->latestReadingValue($componentId, $tomorrow),
                    $definitionMeta[$definitionId]['warning_period'] ?? 7,
                    $definitionMeta[$definitionId]['warning_unit'] ?? 'Days',
                    $today
                );
                $completedAt = null;
                $rhAtCompletion = null;
            }

            PmsJob::create([
                'cfm_guid' => $job['Guid'],
                'vessel_id' => $vesselId,
                'component_id' => $componentId,
                'job_definition_id' => $definitionId,
                'due_date' => $dueDate,
                'original_due_date' => $this->dateOnly($job['OriginalDueDate'] ?? null) ?? $dueDate,
                'due_running_hours' => $dueRh,
                'warning_date' => $warningDate,
                'status' => $status,
                'completed_at' => $completedAt,
                'rh_at_completion' => $rhAtCompletion,
                'remark' => $this->clean($job['Remark'] ?? null),
                'overdue_reason' => $this->clean($job['ReasonForOverdueJobName'] ?? null),
                'overdue_remark' => $this->clean($job['OverdueRemark'] ?? null),
                'reschedule_reason' => $rescheduled ? $this->clean($job['RescheduleReason'] ?? null) : null,
                'reschedule_remark' => $rescheduled ? $this->clean($job['RescheduleRemark'] ?? null) : null,
            ]);
            $result->add('jobs_created');
        }
    }

    /** The latest meter reading on or before today, for statusFor()'s RH-overdue check - no averaging needed here. */
    private function latestReadingValue(string $componentId, string $tomorrow): ?float
    {
        $value = PmsCounterReading::where('component_id', $componentId)->where('reading_date', '<', $tomorrow)
            ->orderByDesc('reading_date')->value('value');

        return $value !== null ? (float) $value : null;
    }

    /** A Completed job for whatever CFM last did, purely so the next due date counts from it (see PmsJobScheduler::baseFor). */
    private function importLastDone(int $vesselId, array $job, string $componentId, string $definitionId, PmsCfmImportResult $result): void
    {
        $cfmGuid = Uuid::uuid5(Uuid::NAMESPACE_OID, $job['Guid'].'|last-done')->toString();
        if (PmsJob::where('cfm_guid', $cfmGuid)->exists()) {
            $result->add('last_done_already_imported');

            return;
        }

        PmsJob::create([
            'cfm_guid' => $cfmGuid,
            'vessel_id' => $vesselId,
            'component_id' => $componentId,
            'job_definition_id' => $definitionId,
            'status' => PmsJob::COMPLETED,
            'completed_at' => $this->dateOnly($job['LastDoneDate']),
            'rh_at_completion' => $job['LastRunningHours'] !== null ? round((float) $job['LastRunningHours'], 1) : null,
            'remark' => 'Imported from CFM',
        ]);
        $result->add('last_done_created');
    }

    // ------------------------------------------------------------------
    // Master data lookups
    // ------------------------------------------------------------------

    private function buildLookups(array $master): array
    {
        $list = fn ($v) => $v['Result'] ?? $v ?? [];
        $byGuid = fn ($rows) => collect($rows)->mapWithKeys(fn ($r) => [$r['Guid'] => $r['Name']])->all();

        return [
            'cfm_rank_name_by_id' => collect($master['ranks'] ?? [])->mapWithKeys(fn ($r) => [$r['ID'] => $r['Name']])->all(),
            'cfm_job_type_name_by_guid' => $byGuid($list($master['jobTypes'] ?? [])),
            'cfm_job_type_abbr_by_guid' => collect($list($master['jobTypes'] ?? []))->mapWithKeys(fn ($r) => [$r['Guid'] => $r['Abbreviation']])->all(),
            'cfm_location_name_by_guid' => $byGuid($list($master['locations'] ?? [])),
            'cfm_category_name_by_guid' => $byGuid($list($master['categories'] ?? [])),
            'cfm_condition_name_by_guid' => $byGuid($master['conditions'] ?? []),
            'cfm_manufacturer_name_by_guid' => $byGuid($master['manufacturers'] ?? []),

            'our_rank_id_by_name' => Rank::all()->mapWithKeys(fn ($r) => [$this->norm($r->name) => $r->id])->all(),
            'our_job_type_id_by_name' => PmsJobType::all()->mapWithKeys(fn ($t) => [$this->norm($t->name) => $t->id])->all(),
            'our_job_type_id_by_abbr' => PmsJobType::all()->mapWithKeys(fn ($t) => [$this->norm($t->abbreviation) => $t->id])->all(),
            'our_location_id_by_name' => PmsLocation::all()->mapWithKeys(fn ($l) => [$this->norm($l->name) => $l->id])->all(),
            'our_category_id_by_name' => PmsComponentCategory::all()->mapWithKeys(fn ($c) => [$this->norm($c->name) => $c->id])->all(),
            'our_manufacturer_id_by_name' => Manufacturer::all()->mapWithKeys(fn ($m) => [$this->norm($m->name) => $m->id])->all(),
            'our_condition_id_by_name' => PmsCondition::all()->mapWithKeys(fn ($c) => [$this->norm($c->name) => $c->id])->all(),
        ];
    }

    private function resolveLocation(?string $guid, array $lookups, PmsCfmImportResult $result): int
    {
        $name = $guid ? ($lookups['cfm_location_name_by_guid'][$guid] ?? null) : null;
        $id = $name ? ($lookups['our_location_id_by_name'][$this->norm($name)] ?? null) : null;
        if ($id) {
            return $id;
        }

        $result->warn('location_unmatched', 'Location "'.($name ?? $guid ?? '(none)').'" not recognised - defaulted to DECK.');

        // CFM's own "Deck Team Maintenance" location, the only one of the
        // six with no equivalent here, is an administrative folder, not
        // equipment - DECK is the closest real location for whatever falls
        // through it or any other location this vessel's app doesn't know.
        return $lookups['our_location_id_by_name']['deck'];
    }

    private function resolveCategory(?string $guid, ?string $fallbackName, array $lookups, PmsCfmImportResult $result): ?int
    {
        $name = ($guid ? ($lookups['cfm_category_name_by_guid'][$guid] ?? null) : null) ?? $fallbackName;
        if (! $name) {
            return null;
        }

        $id = $lookups['our_category_id_by_name'][$this->norm($name)] ?? null;
        if (! $id) {
            $result->warn('category_unmatched', 'Component category "'.$name.'" not recognised - left empty.');
        }

        return $id;
    }

    private function resolveManufacturer(?string $guid, array $lookups, PmsCfmImportResult $result): ?int
    {
        $name = $guid ? trim((string) ($lookups['cfm_manufacturer_name_by_guid'][$guid] ?? '')) : '';
        if ($name === '') {
            return null;
        }

        $id = $lookups['our_manufacturer_id_by_name'][$this->norm($name)] ?? null;
        if (! $id) {
            $result->warn('manufacturer_unmatched', 'Manufacturer "'.$name.'" not recognised - left empty.');
        }

        return $id;
    }

    private function resolveCondition(array $component, array $lookups): int
    {
        $name = $component['ComponentCondition']['Name']
            ?? ($lookups['cfm_condition_name_by_guid'][$component['ComponentConditionGuid'] ?? ''] ?? null);

        return $name ? ($lookups['our_condition_id_by_name'][$this->norm($name)] ?? PmsCondition::defaultId()) : PmsCondition::defaultId();
    }

    private function resolveRank(?int $cfmRankId, string $componentId, array $lookups, PmsCfmImportResult $result): int
    {
        $name = $cfmRankId !== null ? ($lookups['cfm_rank_name_by_id'][$cfmRankId] ?? null) : null;
        $id = $name ? ($lookups['our_rank_id_by_name'][$this->norm($name)] ?? null) : null;
        if ($id) {
            return $id;
        }

        $result->warn('rank_unmatched', 'Rank "'.($name ?? $cfmRankId ?? '(none)').'" not recognised - defaulted by the component\'s location.');

        $onEngine = PmsComponent::whereKey($componentId)->value('location_id') === $lookups['our_location_id_by_name']['engine'];

        return $lookups['our_rank_id_by_name'][$onEngine ? 'chief engineer' : 'chief officer'];
    }

    private function resolveJobType(string $guid, array $lookups, PmsCfmImportResult $result): int
    {
        $name = $lookups['cfm_job_type_name_by_guid'][$guid] ?? null;
        $id = $name ? ($lookups['our_job_type_id_by_name'][$this->norm($name)] ?? null) : null;
        if (! $id) {
            $abbr = $lookups['cfm_job_type_abbr_by_guid'][$guid] ?? null;
            $id = $abbr ? ($lookups['our_job_type_id_by_abbr'][$this->norm($abbr)] ?? null) : null;
        }
        if ($id) {
            return $id;
        }

        $result->warn('job_type_unmatched', 'Job type "'.($name ?? $guid).'" not recognised - defaulted to Maintenance.');

        return $lookups['our_job_type_id_by_name']['maintenance'];
    }

    private function performedBy(?string $code, PmsCfmImportResult $result): string
    {
        $mapped = ['seaman' => 'Crew', 'classsurveyor' => 'ClassSurveyor', 'external' => 'External'][$this->norm((string) $code)] ?? null;
        if ($mapped) {
            return $mapped;
        }

        $result->warn('performer_type_unrecognised', 'Job performer "'.$code.'" not recognised - defaulted to Crew.');

        return 'Crew';
    }

    private function performWhen(?string $code, PmsCfmImportResult $result): string
    {
        if ($code === null || $code === '') {
            return 'Anytime';
        }
        foreach (\App\PmsJobDefinition::PERFORM_WHEN as $value) {
            if ($this->norm($value) === $this->norm($code)) {
                return $value;
            }
        }

        $result->warn('perform_restriction_unrecognised', 'Perform-when "'.$code.'" not recognised - defaulted to Anytime.');

        return 'Anytime';
    }

    private function clean($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function dateOnly(?string $value): ?string
    {
        return $value ? CarbonImmutable::parse($value)->toDateString() : null;
    }

    private function norm(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }
}

/** Thrown only to unwind the DB::transaction() in import() for a --dry-run, then caught there. */
class PmsCfmDryRunRollback extends \RuntimeException
{
}
