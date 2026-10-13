<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One correction to an order line's quantity - who changed it, from what to
 * what, and in what capacity. See the order_item_quantity_changes migration
 * for why this is its own append-only log rather than just overwriting the
 * column.
 */
class OrderItemQuantityChange extends Model
{
    protected $fillable = [
        'order_item_id', 'field', 'old_qty', 'new_qty',
        'changed_by', 'changed_by_name', 'changed_by_role',
    ];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** Live link for anything that wants it - changed_by_name is the record of truth. */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Same display labels RoleController@myApprovals uses for these roles -
     * duplicated rather than shared, since that one is a local variable, not
     * something this model can reach. Falls back to a plain title-cased
     * version for anything not listed, so a future role never prints blank.
     */
    private const ROLE_LABELS = [
        'master' => 'Master',
        'chief-engineer' => 'Chief Engineer',
        'chief-officer' => 'Chief Officer',
        'second-engineer' => 'Second Engineer',
        'gm-srd' => 'GM (SRD)',
        'dgm-srd' => 'DGM (SRD)',
        'agm-srd' => 'AGM (SRD)',
        'am-srd' => 'AM (SRD)',
        'superintendent-srd' => 'Superintendent (SRD)',
        'dgm-ssm' => 'DGM (SSM)',
        'agm-ssm' => 'AGM (SSM)',
        'am-ssm' => 'AM (SSM)',
        'superintendent-ssm' => 'Superintendent (SSM)',
    ];

    public static function roleLabel(?string $role): string
    {
        return self::ROLE_LABELS[$role] ?? ucwords(str_replace('-', ' ', (string) $role));
    }
}
