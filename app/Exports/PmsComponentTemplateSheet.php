<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

/** The Components sheet of the import template: headers, dropdowns, nothing else. */
class PmsComponentTemplateSheet implements FromArray, WithTitle, WithEvents, WithColumnWidths
{
    const HEADERS = [
        'Vessel Code', 'Location *', 'Parent Path', 'Component Name *', 'Category', 'Manufacturer', 'Model / Type',
        'Serial No', 'Installation Date', 'Has Counter (Y/N)', 'Current RH', 'Current RH Date', 'Condition',
        'Critical (Y/N)', 'Class Relevant (Y/N)', 'Class Code', 'Remark',
    ];

    /** Rows the dropdowns and date format reach down to. */
    const LAST_ROW = 2000;

    public function __construct(private PmsComponentTemplateLists $lists)
    {
    }

    public function array(): array
    {
        return [self::HEADERS];
    }

    public function title(): string
    {
        return 'Components';
    }

    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 18, 'C' => 40, 'D' => 40, 'E' => 20, 'F' => 22, 'G' => 18, 'H' => 14, 'I' => 16,
            'J' => 16, 'K' => 12, 'L' => 16, 'M' => 16, 'N' => 13, 'O' => 18, 'P' => 14, 'Q' => 30];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $last = self::LAST_ROW;

            $sheet->getStyle('A1:Q1')->getFont()->setBold(true);
            $sheet->getStyle('A1:Q1')->getFill()->setFillType('solid')->getStartColor()->setRGB('DCEBEF');
            $sheet->freezePane('A2');
            $sheet->getStyle("I2:I{$last}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $sheet->getStyle("L2:L{$last}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');

            // Location / Condition / Y-N must come from the list; Category and
            // Manufacturer only suggest - an unlisted one is just a warning on import.
            $this->dropdown($sheet, 'B', 'Location', true);
            $this->dropdown($sheet, 'E', 'Category', false);
            $this->dropdown($sheet, 'F', 'Manufacturer', false);
            $this->dropdown($sheet, 'J', 'YesNo', true);
            $this->dropdown($sheet, 'M', 'Condition', true);
            $this->dropdown($sheet, 'N', 'YesNo', true);
            $this->dropdown($sheet, 'O', 'YesNo', true);
        }];
    }

    private function dropdown($sheet, string $column, string $list, bool $strict): void
    {
        $range = $this->lists->range($list);
        if (! $range) {
            return;
        }

        $validation = $sheet->getCell($column.'2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle($strict ? DataValidation::STYLE_STOP : DataValidation::STYLE_INFORMATION)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Not in the list')
            ->setError($strict ? 'Pick a value from the list.' : 'Not in the master list - it will be left empty on import.')
            ->setFormula1('='.$range)
            ->setSqref($column.'2:'.$column.self::LAST_ROW);
    }
}
