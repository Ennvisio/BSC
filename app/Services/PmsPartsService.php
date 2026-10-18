<?php

namespace App\Services;

use App\Item;
use App\Manufacturer;
use App\Order;
use App\OrderItem;
use App\PmsJob;
use App\PmsJobDefinition;
use App\PmsRequiredPart;
use App\PmsUsedPart;
use App\StockConsumption;
use App\User;
use App\VesselItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Parts for PMS jobs: turning a job's required parts into a requisition
 * draft, and taking used parts out of stock when a job is completed.
 *
 * It only ever creates a draft Order in the same shape the requisition
 * wizard's first steps do, and moves stock through StockService::consume() -
 * the existing requisition workflow and stock rules are used, not changed.
 */
class PmsPartsService
{
    /** The categories the requisition wizard accepts (RequisitionController::step2). */
    const REQUISITION_CATEGORIES = ['SPR', 'STR', 'CHM', 'LUB', 'PNT'];

    public function __construct(private StockService $stock, private PmsJobScheduler $scheduler)
    {
    }

    /**
     * The catalogue fields a part copies from an item. The catalogue keeps the
     * maker as free text, the parts table as a manufacturers row: matched by
     * name, left empty when the catalogue's maker isn't in that list.
     */
    public function catalogueFields(Item $item): array
    {
        $maker = trim((string) $item->manufacturer);

        return [
            'item_id' => $item->id,
            'part_name' => $item->name,
            'article_no' => $item->article_number,
            'part_no' => $item->part_number,
            'drawing_no' => $item->drawing_number,
            'manufacturer_id' => $maker !== '' ? Manufacturer::whereRaw('lower(name) = ?', [mb_strtolower($maker)])->value('id') : null,
            'unit' => $item->unit,
        ];
    }

    /**
     * Part attributes from form input. A part picked from the catalogue takes
     * its name/article/part/drawing/maker/unit from the item itself; a manual
     * one keeps what was typed. The estimates are always the officer's own.
     */
    public function attributes(array $input): array
    {
        $attributes = [
            'item_id' => null,
            'part_name' => trim((string) ($input['part_name'] ?? '')),
            'article_no' => $this->clean($input['article_no'] ?? null),
            'part_no' => $this->clean($input['part_no'] ?? null),
            'drawing_no' => $this->clean($input['drawing_no'] ?? null),
            'manufacturer_id' => ! empty($input['manufacturer_id']) ? (int) $input['manufacturer_id'] : null,
            'quantity' => max(1, (int) ($input['quantity'] ?? 1)),
            'unit' => trim((string) ($input['unit'] ?? '')),
            'est_delivery_weeks' => isset($input['est_delivery_weeks']) && $input['est_delivery_weeks'] !== '' ? (int) $input['est_delivery_weeks'] : null,
            'est_price' => isset($input['est_price']) && $input['est_price'] !== '' ? round((float) $input['est_price'], 2) : null,
        ];

        if (! empty($input['item_id']) && ($item = Item::find($input['item_id']))) {
            $attributes = array_merge($attributes, $this->catalogueFields($item));
        }

        return $attributes;
    }

    /** Replace a definition's template parts with $parts (the form's list). */
    public function syncTemplate(PmsJobDefinition $definition, array $parts): void
    {
        $definition->requiredParts()->delete();

        foreach ($parts as $part) {
            PmsRequiredPart::create($this->attributes($part) + ['job_definition_id' => $definition->id, 'job_id' => null]);
        }
    }

    /**
     * A part counts as requisitioned only while its order still has a line
     * for that item and the order hasn't been rejected. Anything else - the
     * item taken off the draft in the wizard, the draft thrown away, the
     * requisition rejected - frees it: order_id is cleared so it can be
     * edited and requisitioned again. Safe to call any time; returns how many
     * parts were freed.
     *
     * @param  array<string>|null  $jobIds  only these jobs' parts (all when null)
     */
    public function releaseStaleLinks(?array $jobIds = null): int
    {
        return PmsRequiredPart::whereNotNull('order_id')
            ->when($jobIds !== null, fn ($q) => $q->whereIn('job_id', $jobIds))
            ->where(function ($q) {
                $q->whereNotExists(function ($line) {
                    $line->select(DB::raw(1))->from('order_items')
                        ->whereColumn('order_items.order_id', 'pms_required_parts.order_id')
                        ->whereColumn('order_items.item_id', 'pms_required_parts.item_id');
                })->orWhereExists(function ($order) {
                    $order->select(DB::raw(1))->from('orders')
                        ->whereColumn('orders.id', 'pms_required_parts.order_id')
                        ->where('orders.status', 'rejected');
                });
            })
            ->update(['order_id' => null]);
    }

    /**
     * Take a part back off a requisition that is still a draft: its item's
     * line comes off the draft (or just this part's quantity, if other parts
     * of the same item share the line) and the part is free again.
     */
    public function removeFromRequisition(PmsRequiredPart $part): void
    {
        $this->releaseStaleLinks([(string) $part->job_id]);
        $part->refresh();

        $order = $part->order_id ? Order::find($part->order_id) : null;
        if (! $order) {
            $this->fail('part', 'This part is not on a requisition.');
        }
        if ($order->status !== 'draft') {
            $this->fail('part', 'That requisition has already been submitted, so the part can\'t be taken off it here.');
        }

        DB::transaction(function () use ($part, $order) {
            $line = OrderItem::where('order_id', $order->id)->where('item_id', $part->item_id)->first();
            $sharing = PmsRequiredPart::where('order_id', $order->id)->where('item_id', $part->item_id)->where('id', '!=', $part->id)->exists();

            if ($line && $sharing) {
                $line->item_qty = max(1, (int) $line->item_qty - (int) $part->quantity);
                $line->save();
            } elseif ($line) {
                $line->delete();
            }

            $part->update(['order_id' => null]);
        });
    }

    /**
     * Create a requisition draft for some of a job's parts and link them to
     * it. The draft is what the wizard's Part A step expects to find: the
     * officer carries on from there (justification, details, review, submit).
     *
     * @param  array<int>  $partIds
     */
    public function createRequisition(PmsJob $job, array $partIds, User $user): Order
    {
        $job->loadMissing(['definition', 'component']);

        $this->releaseStaleLinks([$job->id]);
        $parts = PmsRequiredPart::where('job_id', $job->id)->whereIn('id', $partIds)->orderBy('id')->get();
        if ($parts->isEmpty()) {
            $this->fail('parts', 'Select at least one part to requisition.');
        }

        $taken = $parts->filter(fn ($p) => $p->order_id);
        if ($taken->isNotEmpty()) {
            $this->fail('parts', 'Already on a requisition: '.$taken->pluck('part_name')->implode(', ').'.');
        }

        $custom = $parts->filter(fn ($p) => ! $p->item_id);
        if ($custom->isNotEmpty()) {
            $this->fail('parts', 'Not in the item catalogue yet: '.$custom->pluck('part_name')->implode(', ')
                .'. Add each to the catalogue first and pick it from there - a requisition can only hold catalogue items.');
        }

        $items = Item::with('category:id,name,symbol')->whereIn('id', $parts->pluck('item_id'))->get()->keyBy('id');
        $categories = $items->pluck('category')->filter()->unique('id');
        if ($categories->count() > 1) {
            $this->fail('parts', 'A requisition holds one category only, but these parts are in '
                .$categories->pluck('name')->implode(' and ').'. Requisition them separately.');
        }
        $category = $categories->first();
        if (! $category || ! in_array($category->symbol, self::REQUISITION_CATEGORIES, true)) {
            $this->fail('parts', 'Parts in the "'.($category->name ?? 'unknown').'" category cannot be requisitioned here.');
        }

        return DB::transaction(function () use ($job, $parts, $user, $category) {
            $order = new Order;
            $order->vessel_id = $job->vessel_id;
            $order->req_date = now();
            $order->status = 'draft';
            $order->ord_status = false;
            $order->created_by = $user->id;
            $order->created_by_role = $user->role->role ?? '';
            $order->port_name = '';
            $order->title = mb_substr("Requisition for job '".$job->definition->name."'", 0, 191);
            $order->machinery_name = mb_substr($job->component->name, 0, 191);
            $order->category_id = $category->id;
            $order->save();

            // One line per item: two parts that are the same item add up.
            $byItem = $parts->groupBy('item_id')->map(fn ($group) => (int) $group->sum('quantity'));
            $snapshot = $this->stock->snapshotFor($job->vessel_id, $byItem->keys()->all());

            foreach ($byItem as $itemId => $qty) {
                $line = new OrderItem;
                $line->order_id = $order->id;
                $line->item_id = $itemId;
                $line->item_qty = $qty;
                $line->opening_stock = $snapshot[$itemId]['opening_stock'] ?? null;
                $line->last_supply_qty = $snapshot[$itemId]['last_supply_qty'] ?? null;
                $line->last_supply_date = $snapshot[$itemId]['last_supply_date'] ?? null;
                $line->save();
            }

            PmsRequiredPart::whereIn('id', $parts->pluck('id'))->update(['order_id' => $order->id]);

            return $order;
        });
    }

    /**
     * Complete a job and take the parts used for it out of stock, all or
     * nothing: if the completion is refused (late without a reason...) or a
     * quantity is more than the stock, neither the job nor the stock changes.
     */
    public function completeWithUsedParts(PmsJob $job, array $data, array $used, int $userId): PmsJob
    {
        return DB::transaction(function () use ($job, $data, $used, $userId) {
            $job = $this->scheduler->complete($job, $data, $userId);
            $this->recordUsedParts($job, $used, $userId);

            return $job;
        });
    }

    /**
     * Take used parts out of stock for a completed job: one stock_consumptions
     * row each (type 'pms_job') plus a pms_used_parts row linking it to the
     * job. Nothing is taken unless everything can be: a quantity above what's
     * on board fails the lot. Call inside the same transaction as the job's
     * completion so a refusal leaves the job open.
     *
     * @param  array<int,array{item_id:mixed,quantity:mixed}>  $used
     * @return Collection<int,PmsUsedPart>
     */
    public function recordUsedParts(PmsJob $job, array $used, int $userId): Collection
    {
        $job->loadMissing(['definition', 'component']);
        $used = array_values(array_filter($used, fn ($u) => ! empty($u['item_id']) || ! empty($u['quantity'])));

        $errors = [];
        $wanted = [];
        foreach ($used as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $qty = $line['quantity'] ?? null;

            if (! $itemId || ! is_numeric($qty) || (int) $qty != $qty || (int) $qty < 1) {
                $errors['used_parts'] = 'Pick an item and a whole quantity of at least 1 for every used part.';
                break;
            }
            $wanted[$itemId] = ($wanted[$itemId] ?? 0) + (int) $qty;
        }

        if (! $errors) {
            $stock = VesselItem::where('vessel_id', $job->vessel_id)->whereIn('item_id', array_keys($wanted))->pluck('stock_qty', 'item_id');
            $names = Item::whereIn('id', array_keys($wanted))->pluck('name', 'id');

            foreach ($wanted as $itemId => $qty) {
                $onBoard = (int) ($stock[$itemId] ?? 0);
                if (! isset($stock[$itemId]) || $onBoard < 1) {
                    $errors['used_parts'] = ($names[$itemId] ?? 'An item').' has no stock on board.';
                    break;
                }
                if ($qty > $onBoard) {
                    $errors['used_parts'] = 'Only '.$onBoard.' of '.$names[$itemId].' in stock - cannot use '.$qty.'.';
                    break;
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $purpose = "PMS job: {$job->definition->name} ({$job->component->name})";
        $on = $job->completed_at ?? now();

        return DB::transaction(function () use ($wanted, $job, $userId, $purpose, $on) {
            $rows = collect();
            foreach ($wanted as $itemId => $qty) {
                try {
                    $consumption = $this->stock->consume($job->vessel_id, $itemId, $qty, StockConsumption::TYPE_PMS_JOB, $purpose, $on, $userId);
                } catch (RuntimeException $e) {
                    throw ValidationException::withMessages(['used_parts' => $e->getMessage()]);
                }

                $rows->push(PmsUsedPart::create([
                    'job_id' => $job->id,
                    'item_id' => $itemId,
                    'stock_consumption_id' => $consumption->id,
                    'quantity' => $qty,
                ]));
            }

            return $rows;
        });
    }

    /** Requisition number and where it stands, for showing next to a part. */
    public function requisitionInfo(?Order $order): ?array
    {
        if (! $order) {
            return null;
        }

        $draft = $order->status === 'draft';

        return [
            'id' => $order->id,
            'req_no' => $order->req_no,
            'draft' => $draft,
            'status' => $draft ? 'Draft - not submitted yet' : $order->currentStageLabel(),
            'url' => $draft ? route('requisition.form', $order) : route('view.order.detail', $order->id),
        ];
    }

    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
