<?php

namespace App\Exports;

use App\VesselEquipment;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The equipment sheet template: this vessel's current Equipment & Maker List
 * pre-filled, so a re-upload corrects real rows instead of starting blank -
 * same reasoning as StockTemplateExport, minus the null-vs-zero concern that
 * only matters for numeric figures.
 */
class EquipmentTemplateExport implements FromQuery, WithHeadings, WithMapping
{
    private int $vesselId;

    public function __construct(int $vesselId)
    {
        $this->vesselId = $vesselId;
    }

    public function query(): Builder
    {
        return VesselEquipment::query()
            ->where('vessel_id', $this->vesselId)
            ->where('status', true)
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['Equipment Name', 'Maker'];
    }

    public function map($equipment): array
    {
        return [$equipment->name, $equipment->maker];
    }
}
