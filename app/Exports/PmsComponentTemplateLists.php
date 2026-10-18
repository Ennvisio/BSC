<?php

namespace App\Exports;

use App\Manufacturer;
use App\PmsComponentCategory;
use App\PmsCondition;
use App\PmsLocation;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** The master data the template's dropdowns offer, one column per list. */
class PmsComponentTemplateLists implements FromArray, WithTitle, WithStyles
{
    /** Header => column letter, in the order they're written. */
    const COLUMNS = ['Location' => 'A', 'Category' => 'B', 'Condition' => 'C', 'Manufacturer' => 'D', 'YesNo' => 'E'];

    public array $values;

    public function __construct()
    {
        $this->values = [
            'Location' => PmsLocation::ordered()->pluck('name')->all(),
            'Category' => PmsComponentCategory::orderBy('name')->pluck('name')->all(),
            'Condition' => PmsCondition::orderBy('id')->pluck('name')->all(),
            'Manufacturer' => Manufacturer::orderBy('name')->pluck('name')->all(),
            'YesNo' => ['Y', 'N'],
        ];
    }

    /** "Lists!$A$2:$A$7" for a list, or null when it's empty. */
    public function range(string $list): ?string
    {
        $count = count($this->values[$list]);
        $col = self::COLUMNS[$list];

        return $count ? "Lists!\${$col}\$2:\${$col}\$".($count + 1) : null;
    }

    public function array(): array
    {
        $rows = [array_keys(self::COLUMNS)];
        $height = max(array_map('count', $this->values));
        for ($i = 0; $i < $height; $i++) {
            $rows[] = array_map(fn ($list) => $list[$i] ?? null, array_values($this->values));
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Lists';
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
