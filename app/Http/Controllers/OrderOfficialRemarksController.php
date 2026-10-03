<?php

namespace App\Http\Controllers;

use App\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Saves SRD's Official Remarks on a requisition. Independent of Approve /
 * Forward, so an SRD officer can record or revise remarks without taking an
 * approval action. Who may edit is decided by Order::canEditOfficialRemarks().
 */
class OrderOfficialRemarksController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function update(Request $request, Order $order)
    {
        // Checked inside a row lock so two SRD officers submitting at the
        // same moment can't both "add" - the second one sees it's taken.
        $remarks = trim((string) $request->validate([
            'official_remarks' => 'required|string|max:5000',
        ])['official_remarks']);

        if ($remarks === '') {
            return response()->json(['message' => 'Write the Official Remarks before submitting.'], 422);
        }

        $order = \Illuminate\Support\Facades\DB::transaction(function () use ($order, $remarks) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $order->canEditOfficialRemarks(auth()->user()->role->role ?? null)) {
                return null;
            }

            $order->official_remarks = $remarks;
            $order->official_remarks_by = auth()->id();
            $order->official_remarks_at = Carbon::now();
            $order->save();

            return $order;
        });

        if (! $order) {
            return response()->json([
                'message' => 'Official Remarks can only be added once, by an SRD officer, and have already been added or are not open on this requisition.',
            ], 403);
        }

        return response()->json([
            'message' => 'Official Remarks added.',
            'by' => auth()->user()->name,
            'at' => $order->official_remarks_at->format('d M Y'),
        ]);
    }
}
