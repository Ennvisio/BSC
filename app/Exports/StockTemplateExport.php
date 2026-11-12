<?php

namespace App\Exports;

use App\Item;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

/**
 * The stock template: every item in one vessel's catalog for one category,
 * pre-filled with what the system currently believes is on board, so whoever
 * fills it in is correcting real figures rather than typing from scratch.
 *
 * "Stock Qty" is deliberately pre-filled rather than left blank: a blank cell
 * means "leave alone" on import (see VesselStockImport), so pre-filling lets
 * someone change only the handful of lines that are actually wrong.
 *
 * WithStrictNullComparison matters more here than it looks: without it the
 * writer renders a real 0 as an EMPTY cell, which would collide head-on with
 * the blank-means-skip rule above - "we counted it and there are none" would
 * become indistinguishable from "not counted yet".
 */
class StockTemplateExport implements FromQuery, WithHeadings, WithMapping, WithStrictNullComparison
{
    private int $vesselId;
    private int $categoryId;

    public function __construct(int $vesselId, int $categoryId)
    {
        $this->vesselId = $vesselId;
        $this->categoryId = $categoryId;
    }

    public function query(): Builder
    {
        return Item::query()
            ->select('items.id', 'items.name', 'items.article_number', 'items.unit', 'item_groups.path as group_path')
            ->join('vessel_items', 'vessel_items.item_id', '=', 'items.id')
            ->leftJoin('item_groups', 'item_groups.id', '=', 'items.item_group_id')
            ->addSelect('vessel_items.stock_qty', 'vessel_items.opening_stock', 'vessel_items.min_qty')
            ->where('vessel_items.vessel_id', $this->vesselId)
            ->where('items.category_id', $this->categoryId)
            ->where('items.status', true)
            ->orderBy('items.name')
            ->orderBy('items.id');
    }

    /**
     * Item Group sits next to Item Name on purpose: an article number can be
     * shared by several items, and VesselStockImport uses name + group to tell
     * them apart (see its class comment).
     */
    public function headings(): array
    {
        return ['Article Number', 'Item Name', 'Item Group', 'Unit', 'Opening Stock', 'Stock Qty', 'Minimum Qty'];
    }

    public function map($item): array
    {
        return [
            $item->article_number,
            $item->name,
            $item->group_path,
            $item->unit,
            $item->opening_stock,
            $item->stock_qty ?? 0,
            $item->min_qty,
        ];
    }
}
