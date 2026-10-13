<?php

namespace App\Http\Controllers;

use App\Exports\OrderExport;
use App\Order;
use App\RequisitionPrintForm;
use App\Services\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The printable BSC requisition form, and the two other shapes it leaves the
 * app in: PDF (same layout, generated rather than relying on the browser's
 * print-to-PDF) and Excel (the same figures as rows, for pulling into
 * another sheet). A rendered page rather than the old print-pdf-custom.js
 * approach of copying elements out of the detail page into a popup: the
 * form's columns differ per category (see RequisitionPrintForm), which the
 * screen's single fixed table could never produce.
 */
class OrderPrintController extends Controller
{
    /** Blank rows padded out to, so a short requisition still looks like the form. */
    private const MIN_ROWS = 8;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(Order $order)
    {
        return view('layouts.order-print', $this->viewData($order));
    }

    /**
     * Same layout as show(), rendered to a file instead of a browser tab.
     *
     * $forPdf (see viewData()) switches the logo/signature <img> tags to a
     * local filesystem path instead of their public asset() URL - dompdf has
     * no server of its own to fetch an http(s) URL from, and making it dial
     * back into this same app over HTTP just to read a file already on disk
     * is slower and one more way for this to fail in production.
     */
    public function pdf(Order $order)
    {
        $data = $this->viewData($order, forPdf: true);

        $name = str_replace('/', '-', $order->req_no ?: 'requisition-'.$order->id);

        return Pdf::loadView('layouts.order-print', $data)
            ->setPaper('a4', 'portrait')
            ->download($name.'.pdf');
    }

    public function excel(Order $order)
    {
        $this->authorizeViewer($order);
        $order->load(['orderItems.item.itemGroup', 'vessel', 'category']);

        $name = str_replace('/', '-', $order->req_no ?: 'requisition-'.$order->id);

        return Excel::download(new OrderExport($order), $name.'.xlsx');
    }

    /**
     * Visible to anyone who can already open the requisition itself -
     * printing/exporting shows nothing the detail page doesn't.
     */
    private function authorizeViewer(Order $order): void
    {
        $roleRow = auth()->user()->role;

        if (($roleRow->user_type ?? null) === 'ship') {
            abort_unless($order->vessel_id == $roleRow->vessel_id, 403);
        }
    }

    private function viewData(Order $order, bool $forPdf = false): array
    {
        $this->authorizeViewer($order);

        $order->load(['orderItems.item.itemGroup', 'vessel', 'category', 'orderApproval']);

        $liveStock = app(StockService::class)
            ->snapshotFor($order->vessel_id, $order->orderItems->pluck('item_id')->all());

        $symbol = $order->category->symbol ?? null;

        return [
            'order' => $order,
            'liveStock' => $liveStock,
            'columns' => RequisitionPrintForm::columns($symbol),
            'formTitle' => RequisitionPrintForm::title($symbol),
            'signatories' => $order->signatories(),
            'minRows' => self::MIN_ROWS,
            'forPdf' => $forPdf,
        ];
    }
}
