<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * The demo sheet offered on /catalog/import: the exact columns
 * ItemCatalogImport reads (see its class comment), with a few example rows
 * so the "->" Item Path hierarchy and optional columns are clear.
 *
 * Article numbers are obviously fake (DEMO-...) so an example row that is
 * uploaded unedited is easy to spot and clean up later.
 */
class ItemCatalogSampleExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'Item Path', 'Item Name', 'Article Number', 'Unit Code', 'Account Number',
            'Description', 'Part Number', 'Drawing Number', 'HS Code', 'Manufacturer',
        ];
    }

    public function array(): array
    {
        return [
            ['Provisions->Beans and Peas, Dry->BEANS BROAD DRY', 'BEANS BROAD DRY', 'DEMO-0001', 'KG', '5101', 'Dried broad beans, 25 kg bag', '', '', '0713.50', ''],
            ['Provisions->Beans and Peas, Dry->PEAS GREEN DRY', 'PEAS GREEN DRY', 'DEMO-0002', 'KG', '5101', 'Dried green peas', '', '', '0713.10', ''],
            ['Engine Stores->Filters->FUEL FILTER ELEMENT', 'FUEL FILTER ELEMENT', 'DEMO-0003', 'PC', '4210', 'Main engine fuel filter element', 'FF-2210', 'DWG-ME-045', '8421.23', 'Example Marine Ltd'],
            ['Deck Stores->Ropes->MOORING ROPE 48MM', 'MOORING ROPE 48MM', 'DEMO-0004', 'M', '3320', 'Polypropylene mooring rope, 48 mm', '', '', '5607.49', ''],
        ];
    }
}
