<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * The Components sheet as [excel row number => [field => value]]. Headers are
 * matched loosely - case, "*", "(Y/N)" and spacing are ignored - since the
 * template and hand-made sheets label the same column slightly differently
 * ("Has Counter" / "Has Counter (Y/N)", "Location *").
 */
class PmsComponentSheet implements ToCollection
{
    const HEADERS = [
        'vessel code' => 'vessel_code',
        'location' => 'location',
        'parent path' => 'parent_path',
        'parent' => 'parent_path',
        'component name' => 'name',
        'component' => 'name',
        'name' => 'name',
        'category' => 'category',
        'manufacturer' => 'manufacturer',
        'maker' => 'manufacturer',
        'model / type' => 'type',
        'model/type' => 'type',
        'type' => 'type',
        'model' => 'type',
        'serial no' => 'serial_no',
        'serial no.' => 'serial_no',
        'serial number' => 'serial_no',
        'installation date' => 'installation_date',
        'has counter' => 'has_counter',
        'current rh' => 'current_rh',
        'current rh date' => 'current_rh_date',
        'condition' => 'condition',
        'critical' => 'is_critical',
        'class relevant' => 'is_class_relevant',
        'class code' => 'class_code',
        'remark' => 'remark',
        'remarks' => 'remark',
    ];

    /** @var array<int,array<string,mixed>> */
    public array $rows = [];

    /** @var array<int,string> the recognised fields, in column order */
    public array $fields = [];

    public function collection(Collection $rows)
    {
        $columns = [];
        foreach ($rows as $index => $row) {
            $values = $row->toArray();

            if ($index === 0) {
                foreach ($values as $col => $header) {
                    $key = self::HEADERS[self::normalise((string) $header)] ?? null;
                    if ($key && ! in_array($key, $columns, true)) {
                        $columns[$col] = $key;
                    }
                }
                $this->fields = array_values($columns);

                continue;
            }

            $data = [];
            foreach ($columns as $col => $key) {
                $value = $values[$col] ?? null;
                $data[$key] = is_string($value) ? trim($value) : $value;
            }

            if (collect($data)->every(fn ($v) => $v === null || $v === '')) {
                continue;
            }

            $this->rows[$index + 1] = $data;
        }
    }

    public static function normalise(string $header): string
    {
        $header = mb_strtolower(str_replace(['*', '(y/n)'], '', mb_strtolower($header)));

        return trim(preg_replace('/\s+/u', ' ', $header));
    }
}
