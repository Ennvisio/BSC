<?php

namespace App\Console\Commands;

use App\Port;
use Illuminate\Console\Command;

/**
 * Imports the official UN/LOCODE CSV release (unece.org/trade/cefact/UNLOCODE-Download)
 * into the ports table, filtered to actual seaports only.
 *
 * The release ships as several "CodeListPartN.csv" files, unheadered, with
 * columns: ChangeIndicator, Country, Location, Name, NameWoDiacritics,
 * Subdivision, Function, Status, Date, IATA, Coordinates, Remarks.
 *
 * Two row shapes matter here:
 *  - Country header rows: Location is blank, Name is ".COUNTRYNAME" - the
 *    file's own way of listing country names, used to build a code=>name map.
 *  - Location rows: real Function is an 8-char string; position 1 == '1'
 *    means "port" (the other 7 positions cover rail/road/airport/etc and
 *    aren't relevant to a vessel's port of call).
 */
class ImportUnlocode extends Command
{
    protected $signature = 'ports:import {path=storage/app/unlocode : Directory containing the UNLOCODE CodeListPart*.csv files}';

    protected $description = 'Import seaports from a UN/LOCODE CSV release into the ports table';

    public function handle()
    {
        $dir = base_path($this->argument('path'));
        $files = glob($dir.'/UNLOCODE CodeListPart*.csv');

        if (empty($files)) {
            $this->error("No 'UNLOCODE CodeListPart*.csv' files found in {$dir}");

            return self::FAILURE;
        }

        sort($files);

        $countries = [];
        $ports = [];

        foreach ($files as $file) {
            $handle = fopen($file, 'r');

            while (($row = fgetcsv($handle)) !== false) {
                [$change, $countryCode, $locationCode, $name, $nameAscii, $subdivision, $function] = array_pad($row, 7, '');

                if ($locationCode === '' && str_starts_with($name, '.')) {
                    $countries[$countryCode] = ucwords(strtolower(ltrim($name, '.')));

                    continue;
                }

                if ($locationCode !== '' && ($function[0] ?? '') === '1') {
                    $ports[$countryCode.$locationCode] = [
                        'unlocode' => $countryCode.$locationCode,
                        'country_code' => $countryCode,
                        'name' => $name,
                        'name_ascii' => $nameAscii ?: $name,
                    ];
                }
            }

            fclose($handle);
        }

        $rows = array_values(array_map(function ($port) use ($countries) {
            $port['country_name'] = $countries[$port['country_code']] ?? $port['country_code'];
            $port['updated_at'] = now();
            $port['created_at'] = now();

            return $port;
        }, $ports));

        $this->info(count($rows).' seaports found across '.count($files).' file(s), '.count($countries).' countries.');

        foreach (array_chunk($rows, 1000) as $chunk) {
            Port::upsert(
                $chunk,
                ['unlocode'],
                ['country_code', 'country_name', 'name', 'name_ascii', 'updated_at']
            );
        }

        $this->info('Imported. Total ports in database: '.Port::count());

        return self::SUCCESS;
    }
}
