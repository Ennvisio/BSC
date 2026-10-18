<?php

namespace App\Exports;

use App\PmsJob;
use App\Vessel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** The jobs table as shown on /pms/jobs - same columns, same filter. */
class PmsJobsExport implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    const STATUS_LABELS = [
        PmsJob::PLANNED => 'Planned',
        PmsJob::DUE_SOON => 'Due Soon',
        PmsJob::OVERDUE => 'Overdue',
        PmsJob::WAITING_APPROVAL => 'Waiting Approval',
    ];

    const FILTER_LABELS = [
        'all' => 'All open jobs', 'overdue' => 'Overdue', 'duesoon' => 'Due Soon',
        'approval' => 'Waiting Approval', 'critical' => 'Critical',
    ];

    private int $headerRow = 4;

    private array $overdueRows = [];

    public function __construct(private Vessel $vessel, private Collection $rows, private string $filter)
    {
    }

    public function array(): array
    {
        $out = [
            ['Vessel', $this->vessel->name],
            ['Filter', self::FILTER_LABELS[$this->filter] ?? 'All open jobs', 'Exported', now()->format('d/m/Y H:i')],
            [''], // an empty [] row is dropped, which would shift the header up
            ['Rank', 'Job Name', 'Component', 'Type', 'Due Date', 'Due RH', 'Current RH', 'Last Done', 'Status', 'Critical'],
        ];

        foreach ($this->rows as $row) {
            $out[] = [
                $row['rank'],
                $row['name'],
                $row['component_parent'] ? $row['component_parent'].' › '.$row['component'] : $row['component'],
                $row['type_name'],
                $row['due_date'] ? Carbon::parse($row['due_date'])->format('d/m/Y') : '',
                $row['due_running_hours'],
                $row['current_rh'],
                $row['last_done'] ? Carbon::parse($row['last_done'])->format('d/m/Y') : '',
                (self::STATUS_LABELS[$row['status']] ?? $row['status']).($row['rescheduled'] ? ' (rescheduled)' : ''),
                $row['critical'] ? 'Yes' : '',
            ];
            if ($row['status'] === PmsJob::OVERDUE) {
                $this->overdueRows[] = count($out);
            }
        }

        return $out;
    }

    public function title(): string
    {
        return 'PMS Jobs';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:A2')->getFont()->setBold(true);
        $sheet->getStyle('C2')->getFont()->setBold(true);
        $sheet->getStyle('A'.$this->headerRow.':J'.$this->headerRow)->getFont()->setBold(true);
        $sheet->getStyle('A'.$this->headerRow.':J'.$this->headerRow)->getFill()
            ->setFillType('solid')->getStartColor()->setRGB('DCEBEF');
        $sheet->getStyle('F:G')->getNumberFormat()->setFormatCode('#,##0.0');
        foreach ($this->overdueRows as $r) {
            $sheet->getStyle('E'.$r)->getFont()->setBold(true)->getColor()->setRGB('C0392B');
        }
        $sheet->freezePane('A'.($this->headerRow + 1));

        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 9, 'B' => 42, 'C' => 40, 'D' => 18, 'E' => 12, 'F' => 11, 'G' => 11, 'H' => 12, 'I' => 24, 'J' => 9];
    }
}
