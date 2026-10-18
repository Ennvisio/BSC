<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A budget a requisition draws on. Two sets share this table, told apart by
 * `kind`: the stores categories an item requisition uses (Deck Store, Lub Oil,
 * Victualing) and the service ones (Dry-Dock, Plate Renewal). Each picker
 * scopes to its own kind, so the two never mix.
 */
class BudgetGroup extends Model
{
    const KIND_ITEM = 'item';
    const KIND_SERVICE = 'service';

    const KINDS = [
        self::KIND_ITEM => 'Item requisition',
        self::KIND_SERVICE => 'Service requisition',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function serviceRequisitions()
    {
        return $this->hasMany(ServiceRequisition::class);
    }

    /** Live groups of one kind, in the order a picker should list them. */
    public static function ofKind(string $kind)
    {
        return static::where('status', true)
            ->where('kind', $kind)
            ->orderBy('name')
            ->get();
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
