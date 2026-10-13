<?php

namespace App\Exports;

use App\Order;
use App\RequisitionPrintForm;
use App\Services\StockService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The requisition as a spreadsheet - same columns, same order, same figures
 * as the printed BSC form (App\RequisitionPrintForm::columns()), so a sheet
 * pulled for another system cross-checks line for line against the paper
 * copy. A few meta rows (vessel, req no, date, place) lead it the same way
 * they lead the printed form.
 */
class OrderExport implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    private Order $order;

    private array $columns;

    private array $liveStock;

    /** Row index (1-based) the item table's header sits on - styles() bolds it. */
    private int $headerRow;

    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->columns = RequisitionPrintForm::columns($order->category->symbol ?? null);
        $this->liveStock = app(StockService::class)
            ->snapshotFor($order->vessel_id, $order->orderItems->pluck('item_id')->all());
    }

    public function array(): array
    {
        $rows = [
            ['Vessel', $this->order->vessel->name ?? ''],
            ['Requisition No.', $this->order->req_no],
            ['Date', $this->order->req_date ? \Carbon\Carbon::parse($this->order->req_date)->format('d/m/Y') : ''],
            ['Place', $this->order->port_name],
            [],
        ];

        $header = ['SL NO'];
        foreach ($this->columns as $column) {
            $header[] = $column['label'];
        }
        $rows[] = $header;
        $this->headerRow = count($rows);

        foreach ($this->order->orderItems as $index => $line) {
            $row = [$index + 1];
            foreach ($this->columns as $column) {
                $row[] = RequisitionPrintForm::valueFor($column['get'], $line, $this->liveStock);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function title(): string
    {
        $title = $this->order->req_no ?: ('Requisition '.$this->order->id);

        // Sheet names can't carry \ / ? * [ ] : and top out at 31 characters -
        // a req no like "BJ/SPR/01/2026" trips the first rule on its own.
        $title = preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $title);

        return mb_substr($title, 0, 31);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['bold' => true]],
            4 => ['font' => ['bold' => true]],
            $this->headerRow => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 7];
    }
}
