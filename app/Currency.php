<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A currency an invoice can be billed in (ISO 4217 code as the key).
 * See the create_currencies_table migration for why invoices need more than BDT.
 */
class Currency extends Model
{
    const DEFAULT_CODE = 'BDT';

    protected $casts = [
        'is_top' => 'boolean',
        'status' => 'boolean',
    ];

    /**
     * Active currencies for the invoice picker: ['top' => pinned ones in
     * their own order, 'others' => the rest A-Z by name].
     */
    public static function forPicker(): array
    {
        $active = static::where('status', true)->orderBy('sort_order')->get();

        return [
            'top' => $active->where('is_top', true)->values(),
            'others' => $active->where('is_top', false)->sortBy('name')->values(),
        ];
    }

    public static function isActiveCode(?string $code): bool
    {
        return $code !== null && static::where('code', $code)->where('status', true)->exists();
    }

    /** "USD — US Dollar" */
    public function label(): string
    {
        return $this->code.' — '.$this->name;
    }
}
