<?php

namespace App;

/**
 * The printed BSC requisition form, which is a DIFFERENT form per category:
 * Stores asks for an IMPA code, Lub Oil for the equipment and brand, Chemicals
 * for the Unitor code and what the chemical is for. Everything from Unit
 * rightwards is the same on all of them.
 *
 * The printed attachment checklist these forms carry was dropped - the app
 * records attachments against the requisition itself, so a tick-box list of
 * what should have been stapled on has nothing to say here.
 *
 * Kept as a definition here rather than as branches in the print view, so
 * adding a category's form is one entry and the view stays one table.
 */
class RequisitionPrintForm
{
    /**
     * The columns each category's form opens with, before the shared tail.
     * `get` names how to read the value off an order line - resolved in
     * valueFor() rather than stored as a closure, so this stays plain data.
     */
    private const HEAD_COLUMNS = [
        'STR' => [
            ['label' => 'Item Name with full Specifications', 'get' => 'name_with_spec', 'align' => 'left', 'width' => '30%'],
            ['label' => 'IMPA Code', 'get' => 'impa_code', 'width' => '11%'],
        ],
        'LUB' => [
            ['label' => 'Item Name', 'get' => 'name', 'align' => 'left', 'width' => '17%'],
            ['label' => 'Equipment Name', 'get' => 'equipment', 'align' => 'left', 'width' => '13%'],
            ['label' => 'Brand Name', 'get' => 'brand', 'width' => '11%'],
        ],
        'CHM' => [
            ['label' => 'Item Name', 'get' => 'name', 'align' => 'left', 'width' => '15%'],
            ['label' => 'Unitor Code', 'get' => 'article_number', 'width' => '11%'],
            ['label' => 'Purpose of Chemical', 'get' => 'purpose', 'align' => 'left', 'width' => '15%'],
        ],
    ];

    /**
     * Shared by every category, in this order, after the head columns above.
     * These plus the SL NO column come to 59%, so each head set above sums to
     * the remaining 41%.
     */
    private const TAIL_COLUMNS = [
        ['label' => 'Unit', 'get' => 'unit', 'width' => '6%'],
        ['label' => 'Opening Stock', 'get' => 'opening_stock', 'width' => '8%'],
        ['label' => 'Quantity of Last Supply', 'get' => 'last_supply_qty', 'width' => '8%'],
        ['label' => 'Date of Last Supply', 'get' => 'last_supply_date', 'width' => '8%'],
        ['label' => 'In Stock', 'get' => 'in_stock', 'width' => '7%'],
        ['label' => 'Required Quantity', 'get' => 'required_qty', 'width' => '8%'],
        // Filled in by hand after printing - always blank on the page.
        ['label' => 'Office Use', 'get' => null, 'width' => '9%'],
    ];

    /** What the form is titled, where the category has its own wording. */
    private const TITLES = [
        'STR' => 'STORES',
        'LUB' => 'LUB OIL',
        'CHM' => 'CHEMICALS',
        'SPR' => 'SPARES',
        'PNT' => 'PAINT',
    ];

    /** Categories without a form of their own fall back to the Stores one. */
    private const FALLBACK = 'STR';

    private static function key(?string $symbol): string
    {
        $symbol = strtoupper(trim((string) $symbol));

        return isset(self::HEAD_COLUMNS[$symbol]) ? $symbol : self::FALLBACK;
    }

    /** @return array<int,array<string,mixed>> */
    public static function columns(?string $symbol): array
    {
        return array_merge(self::HEAD_COLUMNS[self::key($symbol)], self::TAIL_COLUMNS);
    }

    public static function title(?string $symbol): string
    {
        return self::TITLES[strtoupper(trim((string) $symbol))]
            ?? self::TITLES[self::FALLBACK];
    }

    /**
     * Stand-ins the imported catalog uses for "nothing here" - 44,000+ items
     * carry "-" as their IMPA code and thousands carry "none" as a
     * description. Printing those verbatim fills the form with noise; an empty
     * box is something a person can write in.
     */
    private const PLACEHOLDERS = ['-', '--', '—', 'none', 'n/a', 'na', 'null', 'nil'];

    private static function text(?string $value): string
    {
        $value = trim((string) $value);

        return in_array(mb_strtolower($value), self::PLACEHOLDERS, true) ? '' : $value;
    }

    /**
     * One cell's value. Blank rather than a dash for anything missing - this
     * is a paper form, and an empty box is something a person can write in.
     */
    public static function valueFor(?string $get, OrderItem $line, array $liveStock): string
    {
        $item = $line->item;

        return match ($get) {
            'name' => self::text($item->name ?? null),
            'name_with_spec' => trim(self::text($item->name ?? null)
                .(self::text($item->description ?? null) !== '' ? "\n".self::text($item->description) : '')),
            'impa_code' => self::text($item->impa_code ?? null),
            'article_number' => self::text($item->article_number ?? null),
            'brand' => self::text($item->manufacturer ?? null),
            'purpose' => self::text($item->description ?? null),
            // The catalog group an item sits under is the machinery it belongs
            // to, which is what "Equipment Name" asks for.
            'equipment' => self::text($item->itemGroup->name ?? null),
            'unit' => self::text($item->unit ?? null),
            'opening_stock' => $line->opening_stock !== null ? (string) $line->opening_stock : '',
            'last_supply_qty' => $line->last_supply_qty !== null ? (string) $line->last_supply_qty : '',
            'last_supply_date' => $line->last_supply_date
                ? \Carbon\Carbon::parse($line->last_supply_date)->format('d/m/Y')
                : '',
            'in_stock' => (string) ($liveStock[$line->item_id]['stock_qty'] ?? ''),
            'required_qty' => $line->item_qty !== null ? (string) $line->item_qty : '',
            default => '',
        };
    }
}
