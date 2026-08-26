<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ItemGroup extends Model
{
    protected $fillable = ['parent_id', 'name', 'path'];

    public function parent()
    {
        return $this->belongsTo(ItemGroup::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ItemGroup::class, 'parent_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'item_group_id');
    }
}
