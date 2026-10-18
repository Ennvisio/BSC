<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Reads the "Components" sheet of a component import workbook - and only
 * that one: the sample workbook also carries Instructions / Review / Lists
 * sheets that must not be read as data. What the rows MEAN is decided by
 * App\Services\PmsComponentImporter; this only gets them out of the file.
 */
class PmsComponentImport implements WithMultipleSheets, SkipsUnknownSheets
{
    const SHEET = 'Components';

    public PmsComponentSheet $sheet;

    public bool $sheetMissing = false;

    public function __construct()
    {
        $this->sheet = new PmsComponentSheet;
    }

    public function sheets(): array
    {
        return [self::SHEET => $this->sheet];
    }

    public function onUnknownSheet($sheetName)
    {
        $this->sheetMissing = true;
    }
}
