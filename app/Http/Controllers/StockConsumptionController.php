<?php

namespace App\Http\Controllers;

use App\Attachment;
use App\Item;
use App\Order;
use App\Services\StockService;
use App\StockConsumption;
use App\VesselItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Logging what happens to stock AFTER it comes on board - a requisition
 * brings an item's quantity up, this is what takes it back down again, with
 * a reason attached (see StockConsumption's own doc comment on why there's
 * no approval step).
 *
 * Write access (create/store) is chief-officer/second-engineer only, the
 * same two roles who raise item requisitions in the first place (see the
 * 'member' middleware group in routes/web.php) - they're the ones actually
 * using the items day to day. Master keeps the separate, pre-existing
 * authority to restate stock outright (StockController@update) if the books
 * and the physical store ever disagree; index() (the read-only log) is open
 * to every ship role on that vessel, master and chief-engineer included, for
 * oversight.
 */
class StockConsumptionController extends Controller
{
    private const MAX_KB = 15 * 1024;
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
    ];

    private StockService $stock;

    public function __construct(StockService $stock)
    {
        $this->middleware('auth');
        $this->stock = $stock;
    }

    public function create()
    {
        $this->authorizeMember();

        $vesselId = auth()->user()->role->vessel_id;

        // Recent, real (non-draft) requisitions on this vessel, for the
        // optional "what was this originally requisitioned for" link -
        // capped and newest-first since a vessel can carry years of history
        // and only a recent one is ever plausibly still being consumed from.
        $recentOrders = Order::where('vessel_id', $vesselId)
            ->where('ord_status', true)
            ->orderBy('updated_at', 'desc')
            ->limit(100)
            ->get(['id', 'req_no', 'req_date']);

        return view('layouts.stock-consumption-create', [
            'vessel' => auth()->user()->role->vessel,
            'recentOrders' => $recentOrders,
            'types' => StockConsumption::TYPES,
            'defaultDepartment' => $this->departmentForRole(auth()->user()->role->role),
        ]);
    }

    /** AJAX: search this vessel's own stocked items (stock_qty > 0) - a plain requisition/catalog picker would also offer items with nothing on board, which makes no sense to "consume". */
    public function searchItems(Request $request)
    {
        $this->authorizeMember();

        $term = trim((string) $request->query('q'));
        $vesselId = auth()->user()->role->vessel_id;

        $rows = VesselItem::where('vessel_items.vessel_id', $vesselId)
            ->where('vessel_items.stock_qty', '>', 0)
            ->join('items', 'items.id', '=', 'vessel_items.item_id')
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($q2) use ($term) {
                    $q2->where('items.name', 'like', "%{$term}%")
                        ->orWhere('items.article_number', 'like', "%{$term}%");
                });
            })
            ->orderBy('items.name')
            ->limit(25)
            ->get([
                'vessel_items.item_id', 'items.name', 'items.article_number',
                'items.unit', 'vessel_items.stock_qty',
            ]);

        return response()->json($rows->map(fn ($r) => [
            'id' => $r->item_id,
            'name' => $r->name,
            'article_number' => $r->article_number,
            'unit' => $r->unit,
            'stock_qty' => (int) $r->stock_qty,
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizeMember();

        $request->validate([
            'item_id' => 'required|exists:items,id',
            'consumption_type' => 'required|in:'.implode(',', array_keys(StockConsumption::TYPES)),
            'qty' => 'required|integer|min:1',
            'consumed_on' => 'required|date|before_or_equal:today',
            'department' => 'nullable|in:Deck,Engine',
            'purpose' => 'required|string|max:2000',
            'remarks' => 'nullable|string|max:2000',
            'order_id' => 'nullable|exists:orders,id',
            'attachments.*' => 'nullable|file|max:'.self::MAX_KB.'|mimetypes:'.implode(',', self::ALLOWED_MIMES),
        ]);

        $vesselId = auth()->user()->role->vessel_id;

        // A vessel can only log consuming an item it actually carries -
        // matches StockController@update's own "carriesItem" check.
        $carriesItem = VesselItem::where('vessel_id', $vesselId)
            ->where('item_id', $request->item_id)
            ->exists();

        if (! $carriesItem) {
            return response()->json(['message' => 'This item is not in your vessel\'s catalog.'], 422);
        }

        // A linked requisition has to actually be this vessel's own -
        // otherwise the dropdown's own options are the only thing stopping
        // a hand-rolled POST from linking someone else's requisition.
        if ($request->filled('order_id')) {
            $ownsOrder = Order::where('id', $request->order_id)->where('vessel_id', $vesselId)->exists();
            if (! $ownsOrder) {
                return response()->json(['message' => 'That requisition does not belong to your vessel.'], 422);
            }
        }

        try {
            $consumption = $this->stock->consume(
                $vesselId,
                (int) $request->item_id,
                (int) $request->qty,
                $request->consumption_type,
                $request->purpose,
                $request->consumed_on,
                auth()->id(),
                [
                    'order_id' => $request->order_id ?: null,
                    'department' => $request->department ?: null,
                    'remarks' => $request->remarks ?: null,
                ]
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($request->hasFile('attachments')) {
            $ids = [];
            foreach ($request->file('attachments') as $file) {
                $ids[] = $this->storeAttachment($file)->id;
            }
            $consumption->attachments()->sync($ids);
        }

        $item = Item::find($request->item_id);
        $data = "Logged {$request->qty} {$item->unit} of {$item->name} as "
            .StockConsumption::TYPES[$request->consumption_type].'.';

        return response()->json(['message' => $data, 'redirect' => route('stock-consumption.index')]);
    }

    public function index()
    {
        $vesselId = auth()->user()->role->vessel_id;

        abort_unless(
            (auth()->user()->role->user_type ?? null) === 'ship' && $vesselId,
            403
        );

        $consumptions = StockConsumption::with(['item', 'recordedBy', 'order', 'attachments'])
            ->where('vessel_id', $vesselId)
            ->orderBy('consumed_on', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return view('layouts.stock-consumption-index', compact('consumptions'));
    }

    private function storeAttachment($file): Attachment
    {
        $storedName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('attachments/'.auth()->id(), $storedName, 'local');

        $attachment = new Attachment;
        $attachment->uploaded_by = auth()->id();
        $attachment->title = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $attachment->original_filename = $file->getClientOriginalName();
        $attachment->path = $path;
        $attachment->mime_type = $file->getMimeType();
        $attachment->kind = Attachment::kindForMime($file->getMimeType());
        $attachment->file_size = $file->getSize();
        $attachment->save();

        return $attachment;
    }

    /** Deck for chief-officer, Engine for second-engineer - a sane default the officer can still override, not enforced. */
    private function departmentForRole(?string $role): ?string
    {
        return match ($role) {
            'chief-officer' => 'Deck',
            'second-engineer' => 'Engine',
            default => null,
        };
    }

    private function authorizeMember(): void
    {
        abort_unless(
            in_array(auth()->user()->role->role ?? null, ['chief-officer', 'second-engineer'], true),
            403
        );
    }
}
