<?php

namespace App\Http\Controllers;

use App\ServiceRequisition;
use App\ServiceRequisitionApproval;
use App\ServiceRequisitionItem;
use App\VesselCertificate;
use App\VesselEquipment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Service requisitions cover work that isn't an item pick-list - certificate
 * servicing, class surveys, equipment maintenance, IT support - raised by the
 * same ship officers who raise item requisitions (chief-officer/second-
 * engineer, see the 'member' middleware group in routes/web.php).
 *
 * Unlike an item requisition, this one's approval chain ends at SRD level:
 * there's no SSM/procurement leg, since there's nothing physical for the ship
 * to receive back - it's closed once the work is done and paid for ashore.
 *
 * Its requisition number mirrors an item requisition's shape so the two read
 * the same way - see reqNoFor().
 */
class ServiceRequisitionController extends Controller
{
    /**
     * Shore Repair sends equipment ashore to be repaired; Renewal renews the
     * vessel's certificates. A requisition is one or the other - its lines
     * come from two different sources (the vessel's equipment list vs its
     * certificates), so mixing them in one document has no meaning.
     */
    const TYPE_SHORE_REPAIR = 'shore_repair';
    const TYPE_RENEWAL = 'renewal';

    const TYPES = [
        self::TYPE_SHORE_REPAIR => 'Shore Repair',
        self::TYPE_RENEWAL => 'Renewal',
    ];

    public function create()
    {
        $vesselId = auth()->user()->role->vessel_id;

        // Only categories this vessel actually holds certificates in -
        // offering all twelve would mostly lead to empty selections.
        $certificateCategories = \App\CertificateCategory::where('status', true)
            ->whereHas('vesselCertificates', fn ($q) => $q->where('vessel_id', $vesselId)->where('status', true))
            ->orderBy('name')
            ->get();

        return view('layouts.service-requisition-create', [
            'types' => self::TYPES,
            'certificateCategories' => $certificateCategories,
            'budgetGroups' => \App\BudgetGroup::ofKind(\App\BudgetGroup::KIND_SERVICE),
        ]);
    }

    /**
     * The requisition number, assigned at submit.
     *
     * Same shape as an item requisition (JOY/DK/STR/08/2026), with the
     * service type where the stores category sits: JOY/DK/SHR/01/2026, or
     * RNW for a renewal. The counter is per vessel per year across both
     * service types, matching how the item counter works.
     */
    private function reqNoFor(ServiceRequisition $requisition): string
    {
        $typeCode = $requisition->service_type === self::TYPE_RENEWAL ? 'RNW' : 'SHR';

        // The row exists by the time we number it (its id is what makes the
        // number stable), so it has to be left out of its own count - or the
        // vessel's first service requisition of the year comes out as 02.
        $counter = ServiceRequisition::where('vessel_id', $requisition->vessel_id)
            ->whereYear('req_date', Carbon::now()->year)
            ->where('is_submitted', true)
            ->where('id', '!=', $requisition->id)
            ->count() + 1;

        // SHR/RNW already say this is a service requisition, so no separate
        // SRV segment - it sits where an item requisition's category symbol
        // does, which keeps both numbers the same shape and length.
        return $requisition->vessel->reqNoPrefix()
            .'/'.$typeCode
            .'/'.str_pad($counter, 2, '0', STR_PAD_LEFT)
            .'/'.Carbon::now()->year;
    }

    /**
     * Saves and submits the requisition in one step - unlike the item
     * requisition there's no multi-page wizard to leave a draft behind, so it
     * goes straight into the chain, self-approved by whoever raised it (the
     * same shortcut RequisitionController::submit() takes).
     */
    public function store(Request $request)
    {
        $this->authorizeMember();

        $request->validate([
            'service_type' => 'required|in:'.implode(',', array_keys(self::TYPES)),
            // Scoped to the service kind, so a hand-rolled POST can't charge
            // shore repair to a stores budget like Victualing.
            'budget_group_id' => 'required|exists:budget_groups,id,kind,service,status,1',
            'description' => 'required|string|max:5000',
            'due_date' => 'nullable|date',
            'equipment_id' => 'array',
            'equipment_id.*' => 'integer',
            'equipment_qty' => 'array',
            'equipment_qty.*' => 'integer|min:1',
            'certificate_id' => 'array',
            'certificate_id.*' => 'integer',
        ], [], [
            // Otherwise the messages read "budget group id".
            'budget_group_id' => 'budget group',
        ]);

        $vesselId = auth()->user()->role->vessel_id;
        $isRenewal = $request->service_type === self::TYPE_RENEWAL;

        // Lines are resolved against THIS vessel's own equipment/certificates,
        // so a hand-rolled POST can't attach another ship's - and the ids of
        // the wrong kind for the chosen type are simply not looked at.
        $lines = $isRenewal
            ? $this->renewalLines($request, $vesselId)
            : $this->shoreRepairLines($request, $vesselId);

        if (empty($lines)) {
            return response()->json([
                'message' => $isRenewal
                    ? 'Add at least one certificate from your vessel before submitting.'
                    : 'Add at least one piece of equipment from your vessel before submitting.',
            ], 422);
        }

        $role = auth()->user()->role->role;

        $requisition = DB::transaction(function () use ($request, $vesselId, $lines, $role) {
            $requisition = ServiceRequisition::create([
                'vessel_id' => $vesselId,
                'req_date' => Carbon::now(),
                'service_type' => $request->service_type,
                'budget_group_id' => $request->budget_group_id,
                'due_date' => $request->due_date ?: null,
                'description' => $request->description,
                'is_submitted' => true,
                'status' => 'approved by '.$role,
                'created_by' => auth()->id(),
                'created_by_role' => $role,
            ]);

            $requisition->setRelation('vessel', \App\Vessel::find($vesselId));
            $requisition->req_no = $this->reqNoFor($requisition);
            $requisition->save();

            foreach ($lines as $line) {
                ServiceRequisitionItem::create($line + ['service_requisition_id' => $requisition->id]);
            }

            // Submitting IS the originator's own sign-off, so it goes
            // straight to Master/Chief Engineer rather than sitting in the
            // raiser's own queue.
            ServiceRequisitionApproval::create([
                'service_requisition_id' => $requisition->id,
                'cheif_ofcr_app' => $role === 'chief-officer' ? auth()->id() : null,
                'second_eng_app' => $role === 'second-engineer' ? auth()->id() : null,
            ]);

            return $requisition;
        });

        return response()->json([
            'message' => 'Service requisition '.$requisition->req_no.' submitted successfully!',
            'redirect' => route('service-requisition.index'),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function shoreRepairLines(Request $request, int $vesselId): array
    {
        $ids = array_map('intval', (array) $request->input('equipment_id', []));
        $qtys = array_values((array) $request->input('equipment_qty', []));

        $equipment = VesselEquipment::where('vessel_id', $vesselId)->where('status', true)
            ->whereIn('id', $ids)->get()->keyBy('id');

        $lines = [];
        foreach ($ids as $index => $id) {
            if (! isset($equipment[$id])) {
                continue;
            }

            $lines[] = [
                'vessel_equipment_id' => $id,
                'title' => $equipment[$id]->name,
                'subtitle' => $equipment[$id]->maker,
                'quantity' => max(1, (int) ($qtys[$index] ?? 1)),
            ];
        }

        return $lines;
    }

    /** @return array<int,array<string,mixed>> */
    private function renewalLines(Request $request, int $vesselId): array
    {
        $ids = array_map('intval', (array) $request->input('certificate_id', []));

        $certificates = VesselCertificate::with('category')->where('vessel_id', $vesselId)
            ->where('status', true)->whereIn('id', $ids)->get()->keyBy('id');

        $lines = [];
        foreach ($ids as $id) {
            if (! isset($certificates[$id])) {
                continue;
            }

            $lines[] = [
                'vessel_certificate_id' => $id,
                'title' => $certificates[$id]->title,
                'subtitle' => $certificates[$id]->category->name ?? null,
                // A certificate is renewed once - fixed here too, not just
                // in the form, so the quantity can't be raised by hand.
                'quantity' => 1,
            ];
        }

        return $lines;
    }

    /**
     * AJAX: this vessel's own certificates within one category - what a
     * Renewal line points at. Same "member" access as the rest of this
     * controller, and scoped to the caller's vessel, so one ship can never
     * list another's certificates.
     */
    public function certificatesByCategory(Request $request)
    {
        $request->validate(['category_id' => 'required|exists:certificate_categories,id']);

        $rows = \App\VesselCertificate::where('vessel_id', auth()->user()->role->vessel_id)
            ->where('status', true)
            ->where('category_id', $request->category_id)
            ->orderBy('title')
            ->get(['id', 'title', 'exp_date', 'is_permanent']);

        return response()->json($rows->map(fn ($c) => [
            'id' => $c->id,
            'title' => $c->title,
            'expiry' => $c->is_permanent ? 'Permanent' : (string) $c->exp_date,
        ]));
    }

    private function authorizeMember(): void
    {
        abort_unless(
            in_array(auth()->user()->role->role ?? null, ['chief-officer', 'second-engineer'], true)
                && ! empty(auth()->user()->role->vessel_id),
            403
        );
    }

    /**
     * AJAX: search THIS vessel's own Equipment & Maker List, for a Shore
     * Repair line - the same "member" access as the rest of this controller
     * (see the 'member' middleware group in routes/web.php), not
     * EquipmentController's Master/Chief Engineer management access, since
     * raising a service requisition is a different action from maintaining
     * the list it picks from.
     */
    public function searchEquipment(Request $request)
    {
        $term = trim((string) $request->query('q'));
        $vesselId = auth()->user()->role->vessel_id;

        $rows = \App\VesselEquipment::where('vessel_id', $vesselId)
            ->where('status', true)
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($q2) use ($term) {
                    $q2->where('name', 'like', "%{$term}%")
                        ->orWhere('maker', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'maker']);

        return response()->json($rows);
    }
}
