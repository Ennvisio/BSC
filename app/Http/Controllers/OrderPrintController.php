<?php

namespace App\Http\Controllers;

use App\Order;
use App\RequisitionPrintForm;
use App\Services\StockService;

/**
 * The printable BSC requisition form. A rendered page rather than the old
 * print-pdf-custom.js approach of copying elements out of the detail page into
 * a popup: the form's columns differ per category (see RequisitionPrintForm),
 * which the screen's single fixed table could never produce.
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
        // Visible to anyone who can already open the requisition itself -
        // printing shows nothing the detail page doesn't.
        $roleRow = auth()->user()->role;

        if (($roleRow->user_type ?? null) === 'ship') {
            abort_unless($order->vessel_id == $roleRow->vessel_id, 403);
        }

        $order->load(['orderItems.item.itemGroup', 'vessel', 'category', 'orderApproval']);

        $liveStock = app(StockService::class)
            ->snapshotFor($order->vessel_id, $order->orderItems->pluck('item_id')->all());

        $symbol = $order->category->symbol ?? null;

        return view('layouts.order-print', [
            'order' => $order,
            'liveStock' => $liveStock,
            'columns' => RequisitionPrintForm::columns($symbol),
            'formTitle' => RequisitionPrintForm::title($symbol),
            'signatories' => $order->signatories(),
            'minRows' => self::MIN_ROWS,
        ]);
    }
}
