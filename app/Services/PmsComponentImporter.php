<?php

namespace App\Services;

use App\Imports\PmsComponentImport;
use App\Manufacturer;
use App\PmsComponent;
use App\PmsComponentCategory;
use App\PmsCondition;
use App\PmsCounterReading;
use App\PmsLocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Component import: works out what each row of the sheet would do (preview),
 * then does it - all of it or none of it.
 *
 * A component is identified by its location and its path of names from the
 * top of the tree ("ENGINE | compressed air system > main air compressor"),
 * compared case-insensitively. That one key is used to find a row's parent
 * (in the file, before or after it, or already on the vessel) and to spot a
 * component that already exists, which is skipped - so the same file can be
 * imported again without creating anything twice.
 */
class PmsComponentImporter
{
    const CREATE = 'create';
    const SKIP = 'skip';
    const ERROR = 'error';

    /** A category name in the file that means an existing master-list category under a different name. */
    const CATEGORY_ALIASES = ['purifier / separator' => 'purifier'];

    /** Empty fields on an already-existing component that a matching row is allowed to fill - never overwritten. */
    const FILLABLE_FIELDS = ['category_id', 'manufacturer_id', 'serial_no', 'type', 'class_code'];

    const FILLABLE_LABELS = [
        'category_id' => 'Category', 'manufacturer_id' => 'Manufacturer', 'serial_no' => 'Serial No',
        'type' => 'Model / Type', 'class_code' => 'Class Code',
    ];

    /** Read the Components sheet of a workbook into rows keyed by Excel row number. */
    public function read(string $path): array
    {
        $import = new PmsComponentImport;
        Excel::import($import, $path);

        if ($import->sheetMissing) {
            throw ValidationException::withMessages(['file' => 'The workbook has no sheet named "'.PmsComponentImport::SHEET.'".']);
        }
        foreach (['location' => 'Location', 'name' => 'Component Name'] as $field => $label) {
            if (! in_array($field, $import->sheet->fields, true)) {
                throw ValidationException::withMessages(['file' => 'The Components sheet has no "'.$label.'" column in row 1.']);
            }
        }

        return $import->sheet->rows;
    }

    /**
     * What every row would do, without saving anything.
     *
     * @param  array<int,array<string,mixed>>  $rows  [excel row => fields]
     * @return array{rows: array<int,array>, counts: array{create:int,skip:int,error:int}}
     */
    public function analyse(int $vesselId, array $rows): array
    {
        $lookup = $this->lookups();
        $tree = $this->existingTree($vesselId);
        $existing = $tree['ids'];
        $results = [];

        // Pass 1: each row on its own.
        foreach ($rows as $rowNumber => $row) {
            $results[$rowNumber] = $this->parseRow((int) $rowNumber, $row, $lookup);
        }

        // Where each component is first defined in the file.
        $firstInFile = [];
        foreach ($results as $rowNumber => $r) {
            if ($r['key'] !== null && ! isset($firstInFile[$r['key']])) {
                $firstInFile[$r['key']] = $rowNumber;
            }
        }

        // Pass 2: duplicates and parents, now that the whole file is known.
        foreach ($results as $rowNumber => &$r) {
            if ($r['key'] !== null) {
                if (isset($existing[$r['key']])) {
                    $r['action'] = self::SKIP;
                    $r['warnings'][] = 'Already in this vessel\'s tree - skipped.';

                    // The row can still fill in whatever that component is
                    // missing - never overwrite a value it already has.
                    $fill = $this->fillableUpdates($tree['components'][$r['key']], $r['attributes']);
                    if ($fill) {
                        $r['fill'] = $fill;
                        $r['fill_id'] = $existing[$r['key']];
                        $labels = implode(', ', array_map(fn ($f) => self::FILLABLE_LABELS[$f], array_keys($fill)));
                        $r['warnings'][] = 'Will also fill in its empty '.$labels.'.';
                    }
                } elseif ($firstInFile[$r['key']] !== $rowNumber) {
                    $r['action'] = self::SKIP;
                    $r['warnings'][] = 'Same component as row '.$firstInFile[$r['key']].' - skipped.';
                }

                if ($r['parent_key'] !== null && ! isset($existing[$r['parent_key']]) && ! isset($firstInFile[$r['parent_key']])) {
                    $r['errors'][] = 'Parent "'.$r['parent_path'].'" not found under '.$r['location'].' - neither in this file nor in the vessel\'s tree.';
                }
            }

            if ($r['errors']) {
                $r['action'] = self::ERROR;
            }
        }
        unset($r);

        $counts = [self::CREATE => 0, self::SKIP => 0, self::ERROR => 0, 'updated' => 0];
        foreach ($results as $r) {
            $counts[$r['action']]++;
            if (! empty($r['fill'])) {
                $counts['updated']++;
            }
        }

        return ['rows' => $results, 'counts' => $counts];
    }

    /**
     * Import the rows: everything or nothing. Re-checks first (the tree may
     * have changed since the preview) and refuses while any row has an error.
     *
     * @return array{created:int, skipped:int, updated:int, readings:int}
     */
    public function import(int $vesselId, array $rows, int $userId): array
    {
        $analysis = $this->analyse($vesselId, $rows);
        if ($analysis['counts'][self::ERROR] > 0) {
            throw ValidationException::withMessages([
                'file' => $analysis['counts'][self::ERROR].' row(s) have errors - fix them in the file and upload it again. Nothing was imported.',
            ]);
        }

        // Parents before children: shallower paths first, then file order.
        $toCreate = collect($analysis['rows'])->where('action', self::CREATE)
            ->sortBy(fn ($r) => [$r['depth'], $r['row']])->values();
        $toFill = collect($analysis['rows'])->whereNotNull('fill')->values();

        return DB::transaction(function () use ($vesselId, $toCreate, $toFill, $userId, $analysis) {
            $ids = $this->existingTree($vesselId)['ids'];
            $readings = 0;

            foreach ($toCreate as $r) {
                $component = PmsComponent::create($r['attributes'] + [
                    'vessel_id' => $vesselId,
                    'parent_id' => $r['parent_key'] !== null ? $ids[$r['parent_key']] : null,
                    'sort_order' => $r['row'],
                ]);
                $ids[$r['key']] = $component->id;

                if ($r['reading']) {
                    PmsCounterReading::create([
                        'component_id' => $component->id,
                        'reading_date' => $r['reading']['date'],
                        'value' => $r['reading']['value'],
                        'entered_by' => $userId,
                    ]);
                    $readings++;
                }
            }

            foreach ($toFill as $r) {
                PmsComponent::whereKey($r['fill_id'])->update($r['fill']);
            }

            return [
                'created' => $toCreate->count(),
                'skipped' => $analysis['counts'][self::SKIP],
                'updated' => $toFill->count(),
                'readings' => $readings,
            ];
        });
    }

    // ------------------------------------------------------------------

    private function parseRow(int $rowNumber, array $row, array $lookup): array
    {
        $text = fn ($key) => trim((string) ($row[$key] ?? ''));
        $errors = [];
        $warnings = [];

        $name = $text('name');
        $locationName = $text('location');
        $parentPath = $text('parent_path');
        $segments = $parentPath === '' ? [] : array_map('trim', explode('>', $parentPath));

        if ($name === '') {
            $errors[] = 'Component Name is empty.';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = 'Component Name is longer than 255 characters.';
        }
        if (in_array('', $segments, true)) {
            $errors[] = 'Parent Path "'.$parentPath.'" has an empty name in it - separate names with " > ".';
        }

        $locationId = null;
        if ($locationName === '') {
            $errors[] = 'Location is empty.';
        } elseif (! ($locationId = $lookup['locations'][$this->norm($locationName)] ?? null)) {
            $errors[] = 'Unknown Location "'.$locationName.'".';
        }

        $category = null;
        if ($text('category') !== '') {
            $categoryKey = $this->norm($text('category'));
            $categoryKey = self::CATEGORY_ALIASES[$categoryKey] ?? $categoryKey;
            if (! ($category = $lookup['categories'][$categoryKey] ?? null)) {
                $warnings[] = 'Category "'.$text('category').'" isn\'t in the master list - left empty.';
            }
        }

        $manufacturerId = null;
        if ($text('manufacturer') !== '' && ! ($manufacturerId = $lookup['manufacturers'][$this->norm($text('manufacturer'))] ?? null)) {
            $warnings[] = 'Manufacturer "'.$text('manufacturer').'" isn\'t in the master list - left empty.';
        }

        $conditionId = $lookup['default_condition'];
        if ($text('condition') !== '') {
            $conditionId = $lookup['conditions'][$this->norm($text('condition'))] ?? null;
            if (! $conditionId) {
                $warnings[] = 'Condition "'.$text('condition').'" isn\'t in the master list - set to Not Classified.';
                $conditionId = $lookup['default_condition'];
            }
        }

        $hasCounter = $this->flag($row['has_counter'] ?? null, 'Has Counter', $errors);
        $critical = $this->flag($row['is_critical'] ?? null, 'Critical', $errors);
        $classRelevant = $this->flag($row['is_class_relevant'] ?? null, 'Class Relevant', $errors);

        $installed = $this->date($row['installation_date'] ?? null, 'Installation Date', $errors);

        $reading = null;
        $rh = $row['current_rh'] ?? null;
        if ($rh !== null && $rh !== '') {
            if (! is_numeric($rh) || (float) $rh < 0) {
                $errors[] = 'Current RH "'.$rh.'" isn\'t a number of hours.';
            } elseif ($hasCounter !== true) {
                $errors[] = 'Current RH is given but Has Counter isn\'t Y.';
            } else {
                $date = $this->date($row['current_rh_date'] ?? null, 'Current RH Date', $errors) ?? CarbonImmutable::today()->toDateString();
                $reading = ['value' => round((float) $rh, 1), 'date' => $date];
            }
        } elseif (($row['current_rh_date'] ?? null) !== null && ($row['current_rh_date'] ?? '') !== '') {
            $warnings[] = 'Current RH Date is given without Current RH - ignored.';
        }

        foreach (['type' => 'Model / Type', 'serial_no' => 'Serial No', 'class_code' => 'Class Code'] as $field => $label) {
            if (mb_strlen($text($field)) > 255) {
                $errors[] = $label.' is longer than 255 characters.';
            }
        }
        if (mb_strlen($text('remark')) > 5000) {
            $errors[] = 'Remark is longer than 5000 characters.';
        }

        $key = $locationId && $name !== '' ? $this->key($locationId, array_merge($segments, [$name])) : null;

        return [
            'row' => $rowNumber,
            'name' => $name,
            'location' => $locationName,
            'parent_path' => $parentPath,
            'depth' => count($segments),
            'key' => $key,
            'parent_key' => $locationId && $segments ? $this->key($locationId, $segments) : null,
            'action' => self::CREATE,
            'errors' => $errors,
            'warnings' => $warnings,
            'reading' => $reading,
            'fill' => null,
            'fill_id' => null,
            'attributes' => [
                'location_id' => $locationId,
                'name' => $name,
                'type' => $text('type') ?: null,
                'category_id' => $category?->id,
                'manufacturer_id' => $manufacturerId,
                'serial_no' => $text('serial_no') ?: null,
                'installation_date' => $installed,
                'condition_id' => $conditionId,
                'has_counter' => (bool) $hasCounter,
                // A blank flag follows the category's default, as the Add form does.
                'is_critical' => $critical ?? (bool) $category?->is_critical,
                'is_class_relevant' => $classRelevant ?? (bool) $category?->is_class_relevant,
                'class_code' => $text('class_code') ?: null,
                'remark' => $text('remark') ?: null,
            ],
        ];
    }

    /** Master data, keyed by normalised name. */
    private function lookups(): array
    {
        $byName = fn ($models) => $models->mapWithKeys(fn ($m) => [$this->norm($m->name) => $m])->all();

        return [
            'locations' => collect($byName(PmsLocation::all()))->map->id->all(),
            'categories' => $byName(PmsComponentCategory::all()),
            'manufacturers' => collect($byName(Manufacturer::all()))->map->id->all(),
            'conditions' => collect($byName(PmsCondition::all()))->map->id->all(),
            'default_condition' => PmsCondition::where('name', 'Not Classified')->value('id') ?? PmsCondition::defaultId(),
        ];
    }

    /**
     * Every live component already on the vessel, by key: both its id (for
     * resolving a row's parent) and the component itself (for checking which
     * of its fields are still empty).
     *
     * @return array{ids: array<string,string>, components: array<string,PmsComponent>}
     */
    private function existingTree(int $vesselId): array
    {
        $all = PmsComponent::where('vessel_id', $vesselId)
            ->get(['id', 'name', 'parent_id', 'location_id', 'category_id', 'manufacturer_id', 'serial_no', 'type', 'class_code'])
            ->keyBy('id');
        $ids = [];
        $components = [];

        foreach ($all as $component) {
            $names = [];
            $node = $component;
            // A component whose parent is gone is treated as top-level, as the tree page shows it.
            for ($guard = 0; $node && $guard < 50; $guard++) {
                array_unshift($names, $node->name);
                $node = $node->parent_id ? $all->get($node->parent_id) : null;
            }
            $key = $this->key((int) $component->location_id, $names);
            $ids[$key] = $component->id;
            $components[$key] = $component;
        }

        return ['ids' => $ids, 'components' => $components];
    }

    /** Which of $existing's empty fillable fields $attributes has a value for - never a field it already has. */
    private function fillableUpdates(PmsComponent $existing, array $attributes): array
    {
        $updates = [];
        foreach (self::FILLABLE_FIELDS as $field) {
            $current = $existing->{$field};
            $new = $attributes[$field] ?? null;
            if (($current === null || $current === '') && $new !== null && $new !== '') {
                $updates[$field] = $new;
            }
        }

        return $updates;
    }

    private function key(int $locationId, array $names): string
    {
        return $locationId.'|'.implode(' > ', array_map(fn ($n) => $this->norm($n), $names));
    }

    private function norm(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }

    /** Y / N (also Yes / No / 1 / 0 / TRUE / FALSE); null when the cell is empty. */
    private function flag($value, string $label, array &$errors): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }

        $v = mb_strtolower(trim((string) $value));
        if (in_array($v, ['y', 'yes', '1', 'true'], true)) {
            return true;
        }
        if (in_array($v, ['n', 'no', '0', 'false'], true)) {
            return false;
        }

        $errors[] = $label.' "'.$value.'" must be Y or N.';

        return null;
    }

    /** A date cell (Excel date number, or text like 31/01/2026 or 2026-01-31) as Y-m-d; not in the future. */
    private function date($value, string $label, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = null;
        try {
            if ($value instanceof \DateTimeInterface) {
                $date = CarbonImmutable::instance($value);
            } elseif (is_numeric($value)) {
                $date = CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value));
            } else {
                foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'd M Y', 'd-M-Y', 'j/n/Y'] as $format) {
                    try {
                        $parsed = CarbonImmutable::createFromFormat('!'.$format, (string) $value);
                    } catch (\Throwable) {
                        continue;
                    }
                    if ($parsed && $parsed->format($format) === (string) $value) {
                        $date = $parsed;
                        break;
                    }
                }
            }
        } catch (\Throwable) {
            $date = null;
        }

        if (! $date) {
            $errors[] = $label.' "'.$value.'" isn\'t a date - use DD/MM/YYYY.';

            return null;
        }
        if ($date->startOfDay()->gt(CarbonImmutable::today())) {
            $errors[] = $label.' '.$date->format('d/m/Y').' is in the future.';

            return null;
        }

        return $date->toDateString();
    }
}
