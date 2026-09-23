<?php

namespace App;

use Carbon\Carbon;

/**
 * The "Ship's Spares Demand and Procurement Approval Form" - what each question
 * is, what it can be answered with, and how a submitted answer set is checked.
 *
 * Only the DEFINITION lives here. Answers are stored per requisition per part
 * in order_form_parts (see OrderFormPart), so wording can change later without
 * touching what officers already answered: bump VERSION and keep the old
 * definition around for rows saved under it.
 *
 * Part A is the ship's, completed before the requisition is raised. Part B is
 * SRD's cross-check of it, completed before they approve or delegate. Part C
 * (SSM) will be added to questions() the same way - nothing outside the
 * per-part definitions is specific to any one of them.
 *
 * Shape of a stored answer, per question key:
 *   ['choice' => 'x', 'followup' => 'yes', 'fields' => ['k' => 'v']]
 */
class RequisitionForm
{
    const PART_A = 'A';
    const PART_B = 'B';
    const PART_C = 'C';

    const VERSION = 1;

    /**
     * Display labels. Written out in full rather than as a bare "Part A" -
     * on the order detail page this heading is the only thing naming which
     * form the answers below it came from.
     */
    const PARTS = [
        self::PART_A => "SHIP'S SPARES DEMAND AND PROCUREMENT APPROVAL FORM - PART A",
        self::PART_B => "SHIP'S SPARES DEMAND AND PROCUREMENT APPROVAL FORM - PART B",
        self::PART_C => "SHIP'S SPARES DEMAND AND PROCUREMENT APPROVAL FORM - PART C",
    ];

    /** The strapline under each part's heading on the form it came from. */
    const PART_SUBTITLES = [
        self::PART_A => 'To be completed by ship',
        self::PART_B => 'To be completed by SRD - independent cross-verification against office/technical records',
        self::PART_C => 'To be completed by SSM - final review and approving-authority decision',
    ];

    /** Longest free-text answer accepted in one field. */
    const MAX_FIELD_LENGTH = 100;

    private static function yesNo(): array
    {
        return ['yes' => 'YES', 'no' => 'NO'];
    }

    private static function yesNoNa(): array
    {
        return ['yes' => 'YES', 'no' => 'NO', 'na' => 'N/A'];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function questions(string $part): array
    {
        return match ($part) {
            self::PART_A => self::partA(),
            self::PART_B => self::partB(),
            self::PART_C => self::partC(),
            default => [],
        };
    }

    public static function exists(string $part): bool
    {
        return array_key_exists($part, self::PARTS);
    }

    private static function partA(): array
    {
        return [
            [
                'key' => 'why_required', 'no' => 1, 'required' => true, 'type' => 'single',
                'en' => 'Why is the item required?',
                'options' => [
                    'breakdown' => 'Breakdown/Failure',
                    'end_of_life' => 'Deterioration/End of service life',
                    'planned_maintenance' => 'Planned Maintenance',
                    'critical_spare' => 'Critical/Minimum Spare',
                    'routine_consumption' => 'Routine Consumption',
                    'safety_statutory' => 'Safety/Statutory',
                    'new_installation' => 'New Installation/Modification',
                ],
                'followup' => [
                    'key' => 'need_established',
                    'label' => 'Is the need currently well established?',
                    'options' => self::yesNo(),
                ],
            ],
            [
                'key' => 'why_now', 'no' => 2, 'required' => true, 'type' => 'single',
                'en' => 'Why is procurement needed now?',
                'options' => [
                    'existing_failed' => 'Existing item failed',
                    'unreliable' => 'Unreliable',
                    'maintenance_due' => 'Maintenance due',
                    'spare_consumed' => 'Spare consumed',
                    'below_minimum' => 'Stock below minimum',
                    'upcoming_requirement' => 'Upcoming operational/statutory requirement',
                    'future_requirement' => 'Future requirement',
                ],
                'followup' => [
                    'key' => 'immediate_procurement',
                    'label' => 'Is immediate/advance procurement required?',
                    'options' => self::yesNo(),
                ],
                'fields' => [
                    ['key' => 'required_by', 'label' => 'Required by', 'type' => 'date', 'required' => true],
                ],
            ],
            [
                'key' => 'consequence', 'no' => 3, 'required' => false, 'type' => 'single',
                'en' => 'Consequence if procurement is not completed in time?',
                'options' => [
                    'none' => 'No immediate consequence',
                    'maintenance_delayed' => 'Maintenance delayed',
                    'standby_reduced' => 'Standby reduced',
                    'machinery_affected' => 'Machinery/System affected',
                    'operation_affected' => 'Vessel operation affected',
                    'safety_affected' => 'Safety/Compliance affected',
                ],
                'followup' => [
                    'key' => 'consistent_with_urgency',
                    'label' => 'Consistent with the urgency stated in Q2?',
                    'options' => self::yesNo(),
                ],
            ],
            [
                'key' => 'existing_condition', 'no' => 4, 'required' => false, 'type' => 'single',
                'en' => 'Current condition of the existing item?',
                'options' => [
                    'none' => 'None',
                    'serviceable' => 'Serviceable',
                    'partially_serviceable' => 'Partially serviceable',
                    'failed' => 'Failed',
                    'damaged' => 'Damaged',
                    'worn_out' => 'Worn out',
                    'under_repair' => 'Under repair',
                ],
                'followup' => [
                    'key' => 'replacement_consistent',
                    'label' => 'Is the need for replacement consistent with that condition?',
                    'options' => self::yesNoNa(),
                ],
            ],
            [
                'key' => 'repairable', 'no' => 5, 'required' => false, 'type' => 'single',
                'en' => 'Can the existing item be repaired/overhauled?',
                'options' => [
                    'yes_economical' => 'Yes — economical',
                    'yes_uneconomical' => 'Yes — uneconomical',
                    'yes_time_unsuitable' => 'Yes — time unsuitable',
                    'no_infeasible' => 'No — technically infeasible',
                    'na' => 'N/A',
                ],
                'followup' => [
                    'key' => 'procurement_better',
                    'label' => 'Is new procurement more suitable than repair?',
                    'options' => self::yesNoNa(),
                ],
            ],
            [
                'key' => 'service_history', 'no' => 6, 'required' => false, 'type' => 'single',
                'en' => 'Service history',
                'options' => [
                    'within_life' => 'Within expected life',
                    'life_completed' => 'Service life completed',
                    'failed_early' => 'Failed unusually early',
                    'unavailable' => 'Information unavailable',
                    'na' => 'N/A',
                ],
                'fields' => [
                    ['key' => 'used_since', 'label' => 'Used since', 'type' => 'text'],
                    ['key' => 'service_period', 'label' => 'Approx. service period/hours', 'type' => 'text'],
                ],
                'followup' => [
                    'key' => 'history_consistent',
                    'label' => 'Is the replacement consistent with the service history?',
                    'options' => self::yesNoNa(),
                ],
            ],
            [
                'key' => 'stock_position', 'no' => 7, 'required' => false, 'type' => 'single',
                'en' => 'Stock/Spare position',
                'options' => [
                    'no_usable' => 'No usable stock',
                    'reserved' => 'Critical/minimum stock reserved',
                    'transferable' => 'Transferable stock',
                    'unavailable' => 'Information unavailable',
                ],
                'fields' => [
                    ['key' => 'onboard', 'label' => 'Onboard', 'type' => 'text'],
                    ['key' => 'bsc_store', 'label' => 'BSC Store', 'type' => 'text'],
                    ['key' => 'other_vessel', 'label' => 'Other BSC Vessel', 'type' => 'text'],
                ],
                'followup' => [
                    'key' => 'stock_can_meet',
                    'label' => 'Can the requirement be met from existing BSC stock?',
                    'options' => self::yesNo(),
                ],
            ],
            [
                'key' => 'quantity', 'no' => 8, 'required' => false, 'type' => 'single',
                'en' => 'Quantity demanded',
                'options' => [
                    'one_for_one' => 'One-for-one',
                    'failed_units' => 'Failed units',
                    'planned_maintenance' => 'Planned maintenance',
                    'critical_spare' => 'Critical spare',
                    'minimum_stock' => 'Minimum stock',
                    'historical' => 'Historical consumption',
                    'other' => 'Other',
                ],
                'fields' => [
                    ['key' => 'demanded', 'label' => 'Demanded', 'type' => 'text'],
                ],
                'followup' => [
                    'key' => 'quantity_reconciles',
                    'label' => 'Does the quantity reconcile with stock and requirement?',
                    'options' => self::yesNo(),
                ],
            ],
            [
                'key' => 'other_requirements', 'no' => 9, 'required' => false, 'type' => 'single',
                'en' => 'Any other requirement for the same vessel/equipment?',
                'options' => ['no' => 'NO', 'yes' => 'YES'],
                'followup' => [
                    'key' => 'can_combine',
                    'label' => 'Can it be combined with the same supplier/OEM/agent?',
                    'options' => self::yesNoNa(),
                ],
            ],
        ];
    }

    /**
     * Part B - SRD's own cross-check of what the ship claimed in Part A,
     * against office and technical records. Every question is required: a
     * half-filled verification isn't a verification, and each one carries an
     * escape hatch ("Record unavailable", "cannot verify", "N/A") for the
     * cases where the records genuinely don't answer it.
     */
    private static function partB(): array
    {
        return [
            [
                'key' => 'matches_records', 'no' => 1, 'required' => true, 'type' => 'single',
                'en' => 'Does the stated requirement match available records?',
                'options' => [
                    'yes_maintenance' => 'YES — Maintenance/Operational record',
                    'yes_pms' => 'YES — PMS',
                    'yes_inspection' => 'YES — Technical inspection',
                    'yes_store' => 'YES — Store record',
                    'no' => 'NO',
                    'unavailable' => 'Record unavailable',
                ],
            ],
            [
                'key' => 'failure_supported', 'no' => 2, 'required' => true, 'type' => 'single',
                'en' => 'Is the failure/condition and date supported by records?',
                'options' => [
                    'yes' => 'YES',
                    'no' => 'NO',
                    'partially' => 'Partially',
                    'na' => 'N/A',
                ],
            ],
            [
                'key' => 'history_supports', 'no' => 3, 'required' => true, 'type' => 'single',
                'en' => 'Does service history support the reason for replacement?',
                'options' => [
                    'yes' => 'YES',
                    'no' => 'NO',
                    'cannot_verify' => 'Cannot verify',
                    'na' => 'N/A',
                ],
            ],
            [
                'key' => 'replacement_over_repair', 'no' => 4, 'required' => true, 'type' => 'single',
                'en' => 'Does the technical assessment support replacement over repair?',
                'options' => [
                    'yes' => 'YES',
                    'no_repair_preferable' => 'NO — Repair preferable',
                    'further_assessment' => 'Further assessment',
                    'na' => 'N/A',
                ],
            ],
            [
                'key' => 'stock_matches', 'no' => 5, 'required' => true, 'type' => 'single',
                'en' => "Does stock position match the vessel's statement?",
                'options' => [
                    'yes_no_usable' => 'YES — No usable stock',
                    'no_stock_exists' => 'NO — Stock exists',
                    'transferable' => 'Transferable',
                    'partially_verified' => 'Partially verified',
                ],
                'followup' => [
                    'key' => 'transfer_considered',
                    'label' => 'If stock exists, has transfer/use been considered?',
                    'options' => self::yesNoNa(),
                ],
            ],
            [
                'key' => 'quantity_matches', 'no' => 6, 'required' => true, 'type' => 'single',
                'en' => 'Does quantity match the verified requirement?',
                'options' => [
                    'yes' => 'YES',
                    'no_reduce' => 'NO — Reduce',
                    'no_increase' => 'NO — Increase',
                    'further_assessment' => 'Further assessment',
                ],
                'fields' => [
                    ['key' => 'office_quantity', 'label' => 'Office recommended quantity', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'urgency_supported', 'no' => 7, 'required' => true, 'type' => 'single',
                'en' => 'Do verified facts support the claimed urgency?',
                'options' => [
                    'yes' => 'YES',
                    'no_can_defer' => 'NO — can defer',
                    'alternative_available' => 'Alternative/standby available',
                    'clarification_required' => 'Clarification required',
                ],
            ],
        ];
    }

    /**
     * Part C - SSM's final review before the requisition is assigned for
     * procurement: is the price defensible, is the budget there, does the
     * whole chain of claims hold together.
     *
     * Renumbered 1-8. The paper form runs 1,2,3,4,5,7,8,5 - two questions
     * numbered 5 and no 6 - which would be reproduced here as two colliding
     * keys and a confusing screen.
     */
    private static function partC(): array
    {
        return [
            [
                'key' => 'price_verifiable', 'no' => 1, 'required' => true, 'type' => 'single',
                'en' => 'Is the quoted price independently verifiable?',
                'options' => [
                    'previous_purchase' => 'Previous BSC purchase',
                    'oem_quotation' => 'OEM quotation',
                    'other_quotation' => 'Other supplier quotation',
                    'market_reference' => 'Market reference',
                    'comparative_statement' => 'Comparative statement',
                    'none' => 'No independent reference',
                ],
                'followup' => [
                    'key' => 'price_assessment',
                    'label' => 'Price',
                    'options' => [
                        'reasonable' => 'Reasonable',
                        'higher_justified' => 'Higher but justified',
                        'higher_unjustified' => 'Higher without adequate justification',
                        'cannot_establish' => 'Cannot establish',
                    ],
                ],
            ],
            [
                'key' => 'consolidation', 'no' => 2, 'required' => true, 'type' => 'single',
                'en' => 'Can other requirements for the same vessel be consolidated with the same supplier/OEM/agent?',
                'options' => [
                    'no' => 'NO',
                    'yes_can_combine' => 'YES — can combine',
                    'yes_cannot_combine' => 'YES — cannot reasonably combine',
                    'not_verified' => 'Not verified',
                ],
                'fields' => [
                    ['key' => 'potential_saving', 'label' => 'Potential saving (USD)', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'budget_available', 'no' => 3, 'required' => true, 'type' => 'single',
                'en' => 'Is the required budget available?',
                'options' => self::yesNo(),
                'fields' => [
                    ['key' => 'budget_head', 'label' => 'Budget Head', 'type' => 'text'],
                    ['key' => 'available_balance', 'label' => 'Available balance (BDT)', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'method_compliant', 'no' => 4, 'required' => true, 'type' => 'single',
                'en' => 'Is the procurement method compliant with applicable rules?',
                'options' => [
                    'yes' => 'YES',
                    'no' => 'NO',
                    'clarification_required' => 'Clarification/Correction required',
                ],
            ],
            [
                // No choices - this one is purely a record of what was found.
                'key' => 'last_supplier', 'no' => 5, 'required' => false, 'type' => 'single',
                'en' => 'Last supplier details & price, if tendered',
                'options' => [],
                'fields' => [
                    ['key' => 'supplier', 'label' => 'Last supplier', 'type' => 'text'],
                    ['key' => 'price', 'label' => 'Price', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'performance_guarantee', 'no' => 6, 'required' => true, 'type' => 'single',
                'en' => 'Was a Performance Guarantee of 06 months executed?',
                'options' => self::yesNo(),
            ],
            [
                'key' => 'tender_procedure', 'no' => 7, 'required' => true, 'type' => 'single',
                'en' => 'Tender procedure',
                'options' => [
                    'rfq' => 'RFQ',
                    'dpm' => 'DPM',
                ],
            ],
            [
                'key' => 'consistent_story', 'no' => 8, 'required' => true, 'type' => 'single',
                'en' => 'Does the complete chain of information tell one consistent story?',
                'options' => [
                    'yes' => 'YES',
                    'no_reassessment' => 'NO — Reassessment required',
                ],
            ],
        ];
    }

    /**
     * Turns whatever the browser posted into a stored answer set, and says
     * what's still missing. Only keys and values the definition actually
     * offers survive - a hand-edited POST can't smuggle in an option that
     * isn't on the form.
     *
     * Deliberately forgiving about INCOMPLETE input: an officer saving halfway
     * (Save & Back) gets everything they'd filled in kept, and the missing
     * required answers come back as $errors instead of the whole save failing.
     *
     * @param  array<string,mixed>  $input  the posted q[...] array
     * @return array{answers: array<string,array<string,mixed>>, errors: array<string,string>}
     */
    public static function clean(string $part, array $input): array
    {
        $answers = [];
        $errors = [];

        foreach (self::questions($part) as $q) {
            $raw = is_array($input[$q['key']] ?? null) ? $input[$q['key']] : [];
            $valid = array_keys($q['options']);

            if ($q['type'] === 'multi') {
                $posted = is_array($raw['choice'] ?? null) ? $raw['choice'] : [];
                $choice = array_values(array_intersect($valid, array_filter($posted, 'is_string')));
            } else {
                $posted = $raw['choice'] ?? null;
                $choice = is_string($posted) && in_array($posted, $valid, true) ? $posted : null;
            }

            $followup = null;
            if (isset($q['followup'])) {
                $posted = $raw['followup'] ?? null;
                $followup = is_string($posted) && array_key_exists($posted, $q['followup']['options']) ? $posted : null;
            }

            $fields = [];
            foreach ($q['fields'] ?? [] as $field) {
                $posted = $raw['fields'][$field['key']] ?? '';
                $value = is_scalar($posted) ? trim((string) $posted) : '';
                $value = mb_substr($value, 0, self::MAX_FIELD_LENGTH);

                if ($value !== '' && $field['type'] === 'date' && ! self::isDate($value)) {
                    $value = '';
                }

                $fields[$field['key']] = $value;

                if (($field['required'] ?? false) && $value === '') {
                    $errors[$q['key'].'.'.$field['key']] = "Question {$q['no']}: {$field['label']} is required.";
                }
            }

            if (($q['required'] ?? false) && empty($choice)) {
                $errors[$q['key']] = "Question {$q['no']} needs an answer.";
            }

            $answers[$q['key']] = ['choice' => $choice, 'followup' => $followup, 'fields' => $fields];
        }

        return ['answers' => $answers, 'errors' => $errors];
    }

    private static function isDate(string $value): bool
    {
        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $value);

            return $parsed !== false && $parsed->format('Y-m-d') === $value;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Human label for a stored choice value, for the read-only view. */
    public static function optionLabel(array $question, string $value): string
    {
        return $question['options'][$value] ?? $value;
    }
}
