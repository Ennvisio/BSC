<?php

namespace App\Http\Controllers;

use App\BudgetGroup;
use App\Category;
use App\Order;
use App\OrderApproval;
use App\OrderItem;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The 3-page requisition wizard: header (draft created) -> items (attached to
 * the same draft) -> review + submit (finalizes into the existing approval
 * chain). See app/Http/Controllers/HomeController.php@createOrder/storeOrder
 * for the older single-page flow this supersedes - left in place, unused, for
 * now rather than deleted.
 */
class RequisitionController extends Controller
{
    /**
     * Step 1 doubles as the edit form for a draft that already exists, so
     * Back from step 2 returns here with the details filled in rather than
     * to a blank form that would strand the draft and its items.
     */
    public function createStep1(?Order $order = null)
    {
        if ($order) {
            $this->authorizeDraft($order);
        }

        $budgetGroups = BudgetGroup::where('status', true)->orderBy('name')->get();

        return view('layouts.requisition-step1', compact('budgetGroups', 'order'));
    }

    public function storeStep1(Request $request, ?Order $order = null)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'budget_group_id' => 'required|exists:budget_groups,id',
            'department' => 'required|in:Deck,Engine',
            'port_name' => 'required|string',
        ]);

        // Coming back to step 1 for a draft that already exists updates it -
        // creating a second Order here would orphan the first along with every
        // item already added to it.
        if ($order) {
            $this->authorizeDraft($order);
        } else {
            $order = new Order;
            $order->vessel_id = auth()->user()->role->vessel_id;
            $order->req_date = Carbon::now();
            $order->status = 'draft';
            $order->ord_status = false;
            $order->created_by = auth()->user()->id;
            $order->created_by_role = auth()->user()->role->role;
        }

        $order->title = $request->title;
        $order->budget_group_id = $request->budget_group_id;
        $order->department = $request->department;
        $order->port_name = $request->port_name;
        $order->eta = $request->eta ?: null;
        $order->etd = $request->etd ?: null;
        $order->remarks = $request->remarks;
        $order->high_priority = $request->boolean('high_priority');
        $order->save();

        return redirect()->route('requisition.step2', $order);
    }

    public function step2(Order $order)
    {
        $this->authorizeDraft($order);

        // Only the 5 categories from the management circular (Spares, Stores,
        // Chemicals, Lub Oil, Paint) - not the legacy ad-hoc categories, and
        // not "Imported Catalog" (IMP), which is an internal placeholder for
        // pre-circular uploads rather than something to pick for a new one.
        $categories = Category::whereIn('symbol', ['SPR', 'STR', 'CHM', 'LUB', 'PNT'])
            ->where('status', true)
            ->orderBy('name')
            ->get();

        // Lines already saved against this draft, so coming BACK from the
        // review step shows what's on the requisition instead of an empty
        // table. Without this the page looked like the items had been lost,
        // and re-saving from here would have written a second copy of them.
        $order->load('orderItems.item');

        $liveStock = app(StockService::class)
            ->snapshotFor($order->vessel_id, $order->orderItems->pluck('item_id')->all());

        return view('layouts.requisition-step2', compact('order', 'categories', 'liveStock'));
    }

    public function storeStep2(Request $request, Order $order)
    {
        $this->authorizeDraft($order);

        $request->validate([
            'Category_Name' => 'required|exists:categories,id',
        ]);

        if (empty($request->item_id) || count($request->item_id) === 0) {
            return redirect()->route('requisition.step2', $order)
                ->withErrors(['item_id' => 'Add at least one item before continuing.']);
        }

        $order->category_id = $request->Category_Name;
        $order->save();

        // Stock figures are SNAPSHOTTED onto each line, not read live at
        // display time: a requisition is an audit document, so reprinting one
        // months later has to show the figures that justified it at the time,
        // not whatever is on board today.
        //
        // Opening Stock comes from the vessel's own declared opening balance,
        // NOT from stock_qty - the form prints Opening Stock and In Stock side
        // by side, and sourcing both from stock_qty made them the same number.
        $snapshot = app(StockService::class)->snapshotFor($order->vessel_id, $request->item_id);

        // The submitted list is the WHOLE line-item set for this draft, not an
        // addition to it: step 2 can be revisited (via Back from review, or by
        // resuming the draft later), and appending would leave a second copy
        // of every line each time through.
        OrderItem::where('order_id', $order->id)->delete();

        foreach ($request->item_id as $index => $itemId) {
            $orderItem = new OrderItem;
            $orderItem->order_id = $order->id;
            $orderItem->item_id = $itemId;
            $orderItem->item_qty = $request->item_qty[$index];
            $orderItem->opening_stock = $snapshot[$itemId]['opening_stock'] ?? null;
            $orderItem->last_supply_qty = $snapshot[$itemId]['last_supply_qty'] ?? null;
            $orderItem->last_supply_date = $snapshot[$itemId]['last_supply_date'] ?? null;
            $orderItem->save();
        }

        return redirect()->route('requisition.step3', $order);
    }

    /**
     * AJAX: drop one line from the draft straight away.
     *
     * The delete icon used to only remove the row from the page, leaving the
     * line on the draft until Save & Next - so reloading brought it back,
     * which reads as the delete having silently failed.
     */
    public function destroyStep2Item(Order $order, $itemId)
    {
        $this->authorizeDraft($order);

        OrderItem::where('order_id', $order->id)->where('item_id', $itemId)->delete();

        return response()->json(['message' => 'Item removed from this requisition.']);
    }

    /**
     * AJAX: change one line's required quantity on the spot.
     *
     * Save & Next rewrites the whole line set anyway, so this isn't the only
     * way the figure gets stored - it's here so an edit doesn't quietly revert
     * on the next page load, the same way a delete used to.
     */
    public function updateStep2ItemQty(Request $request, Order $order, $itemId)
    {
        $this->authorizeDraft($order);

        $request->validate(['item_qty' => 'required|integer|min:1']);

        OrderItem::where('order_id', $order->id)
            ->where('item_id', $itemId)
            ->update(['item_qty' => (int) $request->item_qty]);

        return response()->json(['item_qty' => (int) $request->item_qty]);
    }

    public function step3(Order $order)
    {
        $this->authorizeDraft($order);

        $order->load(['orderItems.item', 'category', 'budgetGroup', 'vessel']);

        return view('layouts.requisition-step3', compact('order'));
    }

    public function submit(Order $order)
    {
        $this->authorizeDraft($order);

        // Same counter + symbol format as the existing single-step flow
        // (HomeController@storeOrder) - unchanged, just generated later.
        $counter = Order::where('vessel_id', $order->vessel_id)
            ->whereYear('req_date', Carbon::now()->year)
            ->where('ord_status', true)
            ->count();
        $counter += 1;
        if ($counter < 10) {
            $counter = '0'.$counter;
        }

        $order->req_no = 'DK/'.$order->category->symbol.'/'.$counter.'/'.Carbon::now()->year;
        $order->ord_status = true;

        $orderApproval = new OrderApproval;
        $orderApproval->order_id = $order->id;

        // No separate self-approval click needed - submitting the wizard
        // already represents the originator signing off on their own
        // requisition, so it goes straight to Master/Chief Engineer.
        if ($order->created_by_role === 'chief-officer') {
            $order->status = 'approved by chief-officer';
            $orderApproval->cheif_ofcr_app = $order->created_by;
        } elseif ($order->created_by_role === 'second-engineer') {
            $order->status = 'approved by second-engineer';
            $orderApproval->second_eng_app = $order->created_by;
        } else {
            $order->status = 'ready';
        }

        $order->save();
        $orderApproval->save();

        return redirect('/home/order')->with('message', 'Requisition '.$order->req_no.' submitted successfully!');
    }

    /**
     * A draft can only be resumed/edited by the vessel that started it, and
     * only while it's still a draft - these are now real bookmarkable URLs
     * with an order id in them, not a single AJAX round trip.
     */
    private function authorizeDraft(Order $order): void
    {
        abort_unless($order->vessel_id == auth()->user()->role->vessel_id, 403);
        abort_if($order->status !== 'draft', 403, 'This requisition has already been submitted.');
    }
}
