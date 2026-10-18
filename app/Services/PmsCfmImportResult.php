<?php

namespace App\Services;

/** Counts and warnings from one pms:import-cfm run, for the command to print. */
class PmsCfmImportResult
{
    /** @var array<string,int> */
    public array $counts = [
        'components_created' => 0, 'components_reused' => 0, 'components_already_imported' => 0,
        'readings_created' => 0, 'readings_skipped' => 0,
        'definitions_created' => 0, 'definitions_fleet_split' => 0, 'definitions_already_imported' => 0,
        'definitions_skipped' => 0, 'definitions_excluded' => 0,
        'jobs_created' => 0, 'jobs_already_imported' => 0, 'jobs_skipped' => 0, 'jobs_excluded' => 0,
        'last_done_created' => 0, 'last_done_already_imported' => 0,
    ];

    /** @var array<string,int> warning category => how many times it happened */
    public array $warningCounts = [];

    /** @var array<string,array<string>> warning category => up to 5 example messages */
    public array $warningSamples = [];

    /** @var array<int,array{guid:string,name:string,job_count:int,excluded:bool}> every fleet (no-component) definition found, for review before the real import */
    public array $fleetDefinitions = [];

    public function add(string $counter, int $by = 1): void
    {
        $this->counts[$counter] = ($this->counts[$counter] ?? 0) + $by;
    }

    public function warn(string $category, string $message): void
    {
        $this->warningCounts[$category] = ($this->warningCounts[$category] ?? 0) + 1;
        if (count($this->warningSamples[$category] ?? []) < 5) {
            $this->warningSamples[$category][] = $message;
        }
    }

    public function totalWarnings(): int
    {
        return array_sum($this->warningCounts);
    }
}
