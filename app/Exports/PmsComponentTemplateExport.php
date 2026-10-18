<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** The empty component import workbook: a Components sheet with dropdowns, fed by a Lists sheet. */
class PmsComponentTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $lists = new PmsComponentTemplateLists;

        return [new PmsComponentTemplateSheet($lists), $lists];
    }
}
