<?php

namespace App\Imports;

use App\VesselEquipment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk equipment & maker upload for one vessel.
 *
 * Expected columns (header row): Equipment Name (required), Maker.
 * Matched on (vessel_id, name) - a name already on this vessel's list is
 * UPDATED (its maker replaced), not duplicated, since the same equipment
 * sheet gets re-uploaded as makers change over the ship's life.
 *
 * Unlike VesselStockImport's figures, a blank Maker here is a real answer
 * ("not yet recorded"), not "leave alone" - there's no meaningful reading of
 * a maker column where blank means skip, so a re-upload with an emptied
 * Maker cell does clear it.
 */
class VesselEquipmentImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    private int $vesselId;
    private ?int $userId;

    public int $rowCount = 0;
    public int $upsertedCount = 0;
    public int $skippedCount = 0;
    public int $failedCount = 0;
    /** @var array<int,string> */
    public array $errors = [];

    public function __construct(int $vesselId, ?int $userId)
    {
        $this->vesselId = $vesselId;
        $this->userId = $userId;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $this->rowCount++;

            $name = trim((string) ($row['equipment_name'] ?? ''));
            if ($name === '') {
                $this->skippedCount++;
                continue;
            }

            if (mb_strlen($name) > 255) {
                $this->failedCount++;
                $this->errors[] = 'Row '.$this->rowCount.': Equipment Name is too long (max 255 characters)';
                continue;
            }

            $maker = trim((string) ($row['maker'] ?? ''));

            $payload[$name] = [
                'vessel_id' => $this->vesselId,
                'name' => $name,
                'maker' => $maker !== '' ? $maker : null,
                'status' => true,
                'updated_by' => $this->userId,
                'created_by' => $this->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($payload)) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('vessel_equipment')->upsert(
            array_values($payload),
            ['vessel_id', 'name'],
            ['maker', 'status', 'updated_by', 'updated_at']
        );

        $this->upsertedCount += count($payload);
    }
}
