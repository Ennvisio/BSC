<?php

namespace App\Http\Controllers;

use App\Attachment;
use App\BudgetGroup;
use App\Category;
use App\Order;
use App\OrderApproval;
use App\OrderFormPart;
use App\OrderItem;
use App\RequisitionForm;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The 4-page requisition wizard: header (draft created) -> items (attached to
 * the same draft) -> Part A of the approval form (the ship's justification,
 * see RequisitionForm) -> review + submit (finalizes into the existing
 * approval chain). See app/Http/Controllers/HomeController.php@createOrder/storeOrder
 * for the older single-page flow this supersedes - left in place, unused, for
 * now rather than deleted.
 */
class RequisitionController extends Controller
{
    /**
     * Step 1: the justification form (Part A), asked BEFORE anything else.
     * Nothing exists to attach it to yet, so this renders an unsaved form and
     * storeStep1() is what creates the draft.
     */
    public function createStep1()
    {
        return view('layouts.requisition-form', [
            'order' => null,
            'part' => RequisitionForm::PART_A,
            'questions' => RequisitionForm::questions(RequisitionForm::PART_A),
            // Nothing saved yet - but old() carries answers back when the form
            // comes round again with errors, so a long form isn't retyped.
            'formPart' => null,
        ]);
    }

    /**
     * Saves step 1. Unlike the later steps there's no draft to save a partial
     * answer against, so the form has to be complete before it creates one -
     * an incomplete one comes straight back with the answers still filled in.
     */
    public function storeStep1(Request $request)
    {
        $part = RequisitionForm::PART_A;
        $result = RequisitionForm::clean($part, (array) $request->input('q', []));
        $errors = $result['errors'];

        $declared = $request->boolean('declaration');
        if (! $declared) {
            $errors['declaration'] = 'Tick the declaration to confirm the form is true and correct.';
        }

        if ($errors !== []) {
            return redirect()->route('requisition.step1')->withErrors($errors)->withInput();
        }

        $user = auth()->user();

        $order = new Order;
        $order->vessel_id = $user->role->vessel_id;
        $order->req_date = Carbon::now();
        $order->status = 'draft';
        $order->ord_status = false;
        $order->created_by = $user->id;
        $order->created_by_role = $user->role->role;
        // NOT NULL with no default, and the details step is where it's really
        // asked - a shell draft would otherwise fail to save at all.
        $order->port_name = '';
        $order->save();

        OrderFormPart::create([
            'order_id' => $order->id,
            'part' => $part,
            'form_version' => RequisitionForm::VERSION,
            'answers' => $result['answers'],
            // Stamped as it stood at the time - name and rank as they were
            // when this was declared, not a live lookup of the user later.
            'declaration' => [
                'declared_by' => $user->name,
                'declared_by_role' => $user->role->role ?? null,
                'declared_at' => Carbon::now()->toDateTimeString(),
            ],
            'completed_at' => Carbon::now(),
            'filled_by' => $user->id,
        ]);

        return redirect()->route('requisition.details', $order);
    }

    /** Step 2: the requisition's own details, against the draft step 1 created. */
    public function step2Details(Order $order)
    {
        $this->authorizeDraft($order);

        // Item groups only - the service ones (Dry-Dock, Plate Renewal) share
        // this table but belong to the service requisition's own picker.
        $budgetGroups = BudgetGroup::ofKind(BudgetGroup::KIND_ITEM);

        return view('layouts.requisition-step1', compact('budgetGroups', 'order'));
    }

    public function storeDetails(Request $request, Order $order)
    {
        $this->authorizeDraft($order);

        $request->validate([
            'title' => 'required|string|max:255',
            'reason' => 'required|string',
            'budget_group_id' => 'required|exists:budget_groups,id',
            'department' => 'required|in:Deck,Engine',
            'port_name' => 'required|string',
        ]);

        $order->title = $request->title;
        $order->reason = $request->reason;
        $order->budget_group_id = $request->budget_group_id;
        $order->department = $request->department;
        $order->port_name = $request->port_name;
        $order->eta = $request->eta ?: null;
        $order->etd = $request->etd ?: null;
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
        $order->load('orderItems.item', 'orderItems.attachments.uploader');

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

        // Every attachment currently shown for an item - saved or only just
        // staged - rides along as a hidden attachment_ids[itemId][] input
        // (see requisition-step2.blade.php). Restricted to the caller's own
        // files: a legitimately-preserved id always passes this since it was
        // only ever addable by this same officer, and this is what stops
        // someone linking another user's upload by editing form fields.
        $ownedAttachmentIds = Attachment::where('uploaded_by', auth()->id())->pluck('id')->all();
        $attachmentIdsByItem = (array) $request->input('attachment_ids', []);

        foreach ($request->item_id as $index => $itemId) {
            $orderItem = new OrderItem;
            $orderItem->order_id = $order->id;
            $orderItem->item_id = $itemId;
            $orderItem->item_qty = $request->item_qty[$index];
            $orderItem->opening_stock = $snapshot[$itemId]['opening_stock'] ?? null;
            $orderItem->last_supply_qty = $snapshot[$itemId]['last_supply_qty'] ?? null;
            $orderItem->last_supply_date = $snapshot[$itemId]['last_supply_date'] ?? null;
            $orderItem->save();

            $requestedIds = array_map('intval', $attachmentIdsByItem[$itemId] ?? []);
            $orderItem->attachments()->sync(array_intersect($requestedIds, $ownedAttachmentIds));
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

    /**
     * Step 1 again, for a draft that already has a Part A - what Back from
     * the details step returns to. Every question is rendered from
     * RequisitionForm's definition; this method only supplies the saved
     * answers, so an officer coming back finds what they'd already filled in.
     */
    public function form(Order $order)
    {
        $this->authorizeDraft($order);

        $order->load(['vessel']);
        $formPart = OrderFormPart::where('order_id', $order->id)->where('part', RequisitionForm::PART_A)->first();

        return view('layouts.requisition-form', [
            'order' => $order,
            'part' => RequisitionForm::PART_A,
            'questions' => RequisitionForm::questions(RequisitionForm::PART_A),
            'formPart' => $formPart,
        ]);
    }

    /**
     * Saves an edit to an existing draft's Part A. Always saves what was
     * posted - even half-finished - so a correction never throws away a long
     * form; only whether the part is marked COMPLETE depends on the required
     * answers and the declaration, and only a complete part lets the officer
     * on to Review at the end.
     */
    public function storeForm(Request $request, Order $order)
    {
        $this->authorizeDraft($order);

        $part = RequisitionForm::PART_A;
        $result = RequisitionForm::clean($part, (array) $request->input('q', []));
        $errors = $result['errors'];

        $declared = $request->boolean('declaration');
        if (! $declared) {
            $errors['declaration'] = 'Tick the declaration to confirm the form is true and correct.';
        }

        $complete = $errors === [];
        $user = auth()->user();

        OrderFormPart::updateOrCreate(
            ['order_id' => $order->id, 'part' => $part],
            [
                'form_version' => RequisitionForm::VERSION,
                'answers' => $result['answers'],
                // Stamped as it stood at the time - name and rank as they were
                // when this was declared, not a live lookup of the user later.
                'declaration' => $declared ? [
                    'declared_by' => $user->name,
                    'declared_by_role' => $user->role->role ?? null,
                    'declared_at' => Carbon::now()->toDateTimeString(),
                ] : null,
                'completed_at' => $complete ? Carbon::now() : null,
                'filled_by' => $user->id,
            ]
        );

        if (! $complete) {
            return redirect()->route('requisition.form', $order)->withErrors($errors);
        }

        // Part A is the first step now, so the way on from here is the
        // requisition's own details - not Review, which comes after the items.
        return redirect()->route('requisition.details', $order);
    }

    /** Has this draft's Part A been completed and declared? */
    private function partAComplete(Order $order): bool
    {
        return OrderFormPart::where('order_id', $order->id)
            ->where('part', RequisitionForm::PART_A)
            ->whereNotNull('completed_at')
            ->exists();
    }

    public function step3(Order $order)
    {
        $this->authorizeDraft($order);

        // A draft that skipped ahead (an old bookmark, or one that was already
        // sitting at Review when this step was introduced) goes back to fill it.
        if (! $this->partAComplete($order)) {
            return redirect()->route('requisition.form', $order)
                ->withErrors(['form' => 'Complete the justification form before reviewing the requisition.']);
        }

        // Same table as step 2, minus the inputs - by now storeStep2() has
        // already persisted everything (items and their attachments alike),
        // so this is a plain read of what's actually saved, not a mix of
        // saved-vs-staged state the way step 2 has to handle.
        $order->load(['orderItems.item', 'orderItems.attachments.uploader', 'category', 'budgetGroup', 'vessel']);

        $liveStock = app(StockService::class)
            ->snapshotFor($order->vessel_id, $order->orderItems->pluck('item_id')->all());

        return view('layouts.requisition-step3', compact('order', 'liveStock'));
    }

    public function submit(Order $order)
    {
        $this->authorizeDraft($order);

        // Enforced here as well as at Review: the button being reachable
        // isn't the same as the rule being kept, and a stale tab or a
        // hand-rolled POST goes straight to this.
        if (! $this->partAComplete($order)) {
            return redirect()->route('requisition.form', $order)
                ->withErrors(['form' => 'Complete the justification form before submitting.']);
        }

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

        $order->req_no = $order->vessel->reqNoPrefix().'/'.$order->category->symbol.'/'.$counter.'/'.Carbon::now()->year;
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

        return redirect('/pending/requisition')->with('message', 'Requisition '.$order->req_no.' submitted successfully!');
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
