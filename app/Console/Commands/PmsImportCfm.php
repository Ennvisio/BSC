<?php

namespace App\Console\Commands;

use App\Services\PmsCfmImporter;
use App\Vessel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * One vessel's PMS brought in from a CFM (Cloud Fleet Manager) export - see
 * App\Services\PmsCfmImporter for the field-by-field mapping and the rules
 * around a CFM "fleet" job definition.
 *
 *   php artisan pms:import-cfm AGD 5
 *   php artisan pms:import-cfm AGD 5 --dry-run
 *
 * Reads storage/app/samples/cfm_export/CFM_export_<CODE>.json and
 * CFM_export_master.json. --dry-run runs the whole import and rolls it back
 * at the end, so the counts and warnings are real but nothing is kept.
 */
class PmsImportCfm extends Command
{
    protected $signature = 'pms:import-cfm {code : The vessel\'s CFM internal code, e.g. AGD}
        {vessel_id : This app\'s vessel id to import into}
        {--dry-run : Run the whole import and roll it back - report counts without saving anything}
        {--exclude-def=* : A CFM job definition guid to skip, along with its jobs - repeatable}';

    protected $description = 'Import one vessel\'s PMS (components, counters, job definitions, open jobs) from a CFM export';

    public function handle(PmsCfmImporter $importer): int
    {
        $code = strtoupper($this->argument('code'));
        $vessel = Vessel::find((int) $this->argument('vessel_id'));
        if (! $vessel) {
            $this->error('No vessel with id '.$this->argument('vessel_id').'.');

            return self::FAILURE;
        }

        $dir = storage_path('app/samples/cfm_export');
        $vesselFile = "{$dir}/CFM_export_{$code}.json";
        $masterFile = "{$dir}/CFM_export_master.json";
        foreach (['vessel' => $vesselFile, 'master' => $masterFile] as $label => $path) {
            if (! File::exists($path)) {
                $this->error(ucfirst($label).' export file not found: '.$path);

                return self::FAILURE;
            }
        }

        // Comfortably over what the largest sample file (~16 MB of JSON)
        // expands to as a decoded PHP array.
        ini_set('memory_limit', '1G');

        $vesselData = json_decode(File::get($vesselFile), true, flags: JSON_THROW_ON_ERROR);
        $master = json_decode(File::get($masterFile), true, flags: JSON_THROW_ON_ERROR)['master'] ?? [];

        $dryRun = (bool) $this->option('dry-run');
        $this->info(($dryRun ? '[DRY RUN] ' : '').'Importing CFM export for '.$code.' into '.$vessel->name.' (vessel #'.$vessel->id.')…');

        $result = $importer->import($vessel->id, $vesselData, $master, (int) auth()->id() ?: 1, $dryRun, (array) $this->option('exclude-def'));

        if ($dryRun) {
            $this->printFleetDefinitions($result);
        }
        $this->printCounts($result);
        $this->printWarnings($result);

        if ($result->counts['jobs_excluded'] || $result->counts['definitions_excluded']) {
            $this->newLine();
            $this->line('Skipped: '.$result->counts['jobs_excluded'].' jobs from excluded definitions ('.$result->counts['definitions_excluded'].' definitions).');
        }

        if ($dryRun) {
            $this->comment('Dry run only - nothing was saved.');
        } else {
            $this->info('Import committed.');
        }

        return self::SUCCESS;
    }

    /** "by category" fleet definitions - no fixed component - so an admin can review and --exclude-def any before the real import. */
    private function printFleetDefinitions(\App\Services\PmsCfmImportResult $result): void
    {
        if (! $result->fleetDefinitions) {
            return;
        }

        $this->newLine();
        $this->line('Fleet definitions (no single component):');
        $this->table(['Name', 'Guid', 'Open jobs', 'Excluded'], array_map(
            fn ($d) => [$d['name'], $d['guid'], $d['job_count'], $d['excluded'] ? 'yes' : ''],
            $result->fleetDefinitions
        ));
    }

    private function printCounts(\App\Services\PmsCfmImportResult $result): void
    {
        $this->newLine();
        $this->table(['', 'Created', 'Already imported', 'Reused', 'Skipped'], [
            ['Components', $result->counts['components_created'], $result->counts['components_already_imported'], $result->counts['components_reused'], ''],
            ['Counter readings', $result->counts['readings_created'], '', '', $result->counts['readings_skipped'].' (already covered)'],
            ['Job definitions', $result->counts['definitions_created'].' ('.$result->counts['definitions_fleet_split'].' from fleet definitions)', $result->counts['definitions_already_imported'], '', $result->counts['definitions_skipped'].' ('.$result->counts['definitions_excluded'].' excluded)'],
            ['Open jobs', $result->counts['jobs_created'], $result->counts['jobs_already_imported'], '', $result->counts['jobs_skipped'].' ('.$result->counts['jobs_excluded'].' excluded)'],
            ['Last-done history', $result->counts['last_done_created'], $result->counts['last_done_already_imported'], '', ''],
        ]);
    }

    private function printWarnings(\App\Services\PmsCfmImportResult $result): void
    {
        if (! $result->totalWarnings()) {
            return;
        }

        $this->newLine();
        $this->warn($result->totalWarnings().' warning(s):');
        foreach ($result->warningCounts as $category => $count) {
            $this->line(" - <comment>{$category}</comment>: {$count}");
            foreach ($result->warningSamples[$category] as $sample) {
                $this->line('     e.g. '.$sample);
            }
        }
    }
}
