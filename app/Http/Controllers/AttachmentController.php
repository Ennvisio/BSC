<?php

namespace App\Http\Controllers;

use App\Attachment;
use App\Order;
use App\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Per-item requisition attachments (see the approved UX proposal: upload
 * once into a personal library, reuse across items, view-only for anyone
 * past the requesting vessel).
 *
 * Only chief-officer/second-engineer ever call the write endpoints here
 * (upload/attach/detach), and only through the wizard's Add Items step -
 * enforced by requiring the target line's order to still be a draft owned by
 * the caller's own vessel (authorizeDraftItem()), the same rule
 * RequisitionController::authorizeDraft() applies everywhere else in the
 * wizard. The read endpoint (forItem) is open to any authenticated user,
 * matching this app's existing order-detail page - not a step backward from
 * what's already there, just not a step ahead of it either.
 *
 * Routes address a line the same way the wizard's existing Req Qty/remove
 * routes already do - {order}/items/{itemId} where itemId is the CATALOG
 * item id, not order_items' own primary key. A given catalog item only ever
 * has one row per order (adding it again replaces the row - see
 * requisition-step2.blade.php's commit-staged-items), so that pair
 * addresses one line unambiguously and the frontend never has to know about
 * order_items.id at all.
 */
class AttachmentController extends Controller
{
    private const MAX_KB = 15 * 1024;
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    /** AJAX: the current user's own library, for the "choose from my files" tab. */
    public function myFiles(Request $request)
    {
        $term = trim((string) $request->query('q'));

        $files = Attachment::where('uploaded_by', auth()->id())
            ->when($term !== '', fn ($q) => $q->where('title', 'like', "%{$term}%"))
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'kind', 'created_at']);

        return response()->json($files->map(fn ($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'kind' => $a->kind,
            'uploaded_at' => $a->created_at->diffForHumans(),
        ]));
    }

    /** AJAX: upload a new file straight onto one item's line. */
    public function upload(Request $request, Order $order, $itemId)
    {
        $orderItem = $this->authorizeDraftItem($order, $itemId);

        $attachment = $this->storeUploadedFile($request);

        $orderItem->attachments()->syncWithoutDetaching([$attachment->id]);

        return response()->json($this->itemAttachmentsPayload($orderItem));
    }

    /**
     * AJAX: upload a file into the library only - no item to link it to yet.
     *
     * Covers a row the wizard has staged but not yet saved: it can't be
     * linked via attachment_order_item until Save & Next actually creates the
     * order_items row, but there is no reason the FILE itself has to wait -
     * uploading is independent of any item, only the link isn't. The wizard
     * carries the returned id forward as a hidden `attachment_ids[]` input on
     * that row, and RequisitionController::storeStep2() links it once the
     * item is real.
     */
    public function uploadToLibrary(Request $request)
    {
        $attachment = $this->storeUploadedFile($request);

        return response()->json([
            'id' => $attachment->id,
            'title' => $attachment->title,
            'kind' => $attachment->kind,
            'uploaded_by' => auth()->user()->name ?? '',
            'uploaded_at' => $attachment->created_at->diffForHumans(),
            'view_url' => url('/attachments/'.$attachment->id.'/view'),
        ]);
    }

    private function storeUploadedFile(Request $request): Attachment
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|max:'.self::MAX_KB.'|mimetypes:'.implode(',', self::ALLOWED_MIMES),
        ]);

        $file = $request->file('file');
        $storedName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('attachments/'.auth()->id(), $storedName, 'local');

        $attachment = new Attachment;
        $attachment->uploaded_by = auth()->id();
        $attachment->title = trim($request->title);
        $attachment->original_filename = $file->getClientOriginalName();
        $attachment->path = $path;
        $attachment->mime_type = $file->getMimeType();
        $attachment->kind = Attachment::kindForMime($file->getMimeType());
        $attachment->file_size = $file->getSize();
        $attachment->save();

        return $attachment;
    }

    /** AJAX: link one or more already-uploaded files (from "my files") onto an item. */
    public function attach(Request $request, Order $order, $itemId)
    {
        $orderItem = $this->authorizeDraftItem($order, $itemId);

        $request->validate(['attachment_ids' => 'required|array|min:1']);

        // Only the caller's own files can be attached - "my files" is
        // personal, not a way to pull in someone else's upload by guessing an id.
        $ownedIds = Attachment::where('uploaded_by', auth()->id())
            ->whereIn('id', $request->attachment_ids)
            ->pluck('id');

        $orderItem->attachments()->syncWithoutDetaching($ownedIds);

        return response()->json($this->itemAttachmentsPayload($orderItem));
    }

    /** AJAX: unlink one attachment from one item (the file itself stays in the library). */
    public function detach(Order $order, $itemId, Attachment $attachment)
    {
        $orderItem = $this->authorizeDraftItem($order, $itemId);

        $orderItem->attachments()->detach($attachment->id);

        return response()->json($this->itemAttachmentsPayload($orderItem));
    }

    /** AJAX: every attachment on one item - the wizard's own chip list, and the approver's "See attachments" modal. */
    public function forItem(Order $order, $itemId)
    {
        $orderItem = OrderItem::where('order_id', $order->id)->where('item_id', $itemId)->firstOrFail();

        return response()->json($this->itemAttachmentsPayload($orderItem));
    }

    /**
     * AJAX: permanently remove a file from the officer's own library - the
     * "Choose from my files" tab is the only place a file is visible outside
     * any one item, so it's the natural place to clean the library up too.
     *
     * Refused while the file is still attached to anything, draft or
     * submitted: attachment_order_item cascades on delete, and silently
     * pulling a spec sheet out from under a requisition an approver may
     * already be looking at is exactly the kind of thing this app has
     * otherwise been careful never to do to submitted paperwork. Detach it
     * from those items first (only possible while still a draft - see
     * authorizeDraftItem), then delete.
     */
    public function destroy(Attachment $attachment)
    {
        abort_unless($attachment->uploaded_by === auth()->id(), 403);

        $linkedCount = $attachment->orderItems()->count();
        if ($linkedCount > 0) {
            return response()->json([
                'message' => "This file is attached to {$linkedCount} requisition item"
                    .($linkedCount === 1 ? '' : 's')
                    .' - remove it from those first.',
            ], 422);
        }

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return response()->json(['message' => 'File deleted.']);
    }

    /** Stream one file for viewing/downloading. */
    public function view(Attachment $attachment)
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response(
            $attachment->path,
            $attachment->original_filename,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    private function itemAttachmentsPayload(OrderItem $orderItem): array
    {
        return $orderItem->attachments()->get()->map(fn ($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'kind' => $a->kind,
            'uploaded_by' => $a->uploader->name ?? '',
            'uploaded_at' => $a->pivot->created_at?->diffForHumans() ?? $a->created_at->diffForHumans(),
            'view_url' => url('/attachments/'.$a->id.'/view'),
        ])->values()->all();
    }

    /**
     * Write access to an item's attachments requires the caller's own vessel
     * to own the order AND the order to still be a draft - the wizard is the
     * only place this UI renders, so this is the same authorization
     * RequisitionController::authorizeDraft() already applies to every other
     * step-2 action. Also resolves (order, catalog itemId) to the actual
     * OrderItem row, creating it as an empty attachment holder is never
     * valid - the line has to already exist.
     */
    private function authorizeDraftItem(Order $order, $itemId): OrderItem
    {
        abort_unless($order->vessel_id == auth()->user()->role->vessel_id, 403);
        abort_if($order->status !== 'draft', 403, 'This requisition has already been submitted.');

        return OrderItem::where('order_id', $order->id)->where('item_id', $itemId)->firstOrFail();
    }
}
