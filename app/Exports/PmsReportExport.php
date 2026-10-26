<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Any PMS report as a sheet: a few "label: value" lines (report, scope,
 * filters, export time), a blank line, the column headings, then the rows -
 * the same mapped rows and columns the report page shows.
 */
class PmsReportExport implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    private int $headerRow;

    /**
     * @param  array<string,string>  $meta  label => value lines above the table
     * @param  array<string,string>  $columns  row key => heading
     * @param  array<int,array>  $rows  display rows keyed like $columns
     * @param  array<string,callable>  $format  row key => fn ($value) for the cell
     */
    public function __construct(
        private string $title,
        private array $meta,
        private array $columns,
        private array $rows,
        private array $format = [],
    ) {
    }

    public function array(): array
    {
        $out = [];
        foreach ($this->meta as $label => $value) {
            $out[] = [$label, $value];
        }
        $out[] = ['']; // an empty [] row is dropped, which would shift the header up
        $out[] = array_values($this->columns);
        $this->headerRow = count($out);

        foreach ($this->rows as $row) {
            $line = [];
            foreach (array_keys($this->columns) as $key) {
                $value = $row[$key] ?? null;
                $line[] = isset($this->format[$key]) ? ($this->format[$key])($value, $row) : $value;
            }
            $out[] = $line;
        }

        return $out;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle('A1:A'.($this->headerRow - 2))->getFont()->setBold(true);
        $sheet->getStyle("A{$this->headerRow}:{$lastCol}{$this->headerRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$this->headerRow}:{$lastCol}{$this->headerRow}")->getFill()->setFillType('solid')->getStartColor()->setRGB('DCEBEF');
        $sheet->freezePane('A'.($this->headerRow + 1));

        return [];
    }

    /** The header row's position, for anyone reading the sheet back. */
    public function headerRow(): int
    {
        return $this->headerRow;
    }
}
