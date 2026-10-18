<?php

namespace App;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * One piece of equipment in a vessel's PMS tree. parent_id null = a
 * top-level system; children always share their parent's location so the
 * tree groups cleanly by location.
 */
class PmsComponent extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'cfm_guid', 'vessel_id', 'location_id', 'parent_id', 'name', 'type', 'category_id', 'manufacturer_id',
        'serial_no', 'part_no', 'class_code', 'internal_code', 'installation_date', 'condition_id',
        'has_counter', 'is_critical', 'is_class_relevant', 'is_inactive', 'remark', 'sort_order',
    ];

    protected $casts = [
        'installation_date' => 'date',
        'has_counter' => 'boolean',
        'is_critical' => 'boolean',
        'is_class_relevant' => 'boolean',
        'is_inactive' => 'boolean',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function location()
    {
        return $this->belongsTo(PmsLocation::class, 'location_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function category()
    {
        return $this->belongsTo(PmsComponentCategory::class, 'category_id');
    }

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function condition()
    {
        return $this->belongsTo(PmsCondition::class, 'condition_id');
    }

    public function counterReadings()
    {
        return $this->hasMany(PmsCounterReading::class, 'component_id')->orderByDesc('reading_date');
    }

    public function jobDefinitions()
    {
        return $this->hasMany(PmsJobDefinition::class, 'component_id');
    }

    public function jobs()
    {
        return $this->hasMany(PmsJob::class, 'component_id');
    }

    public function latestReading()
    {
        return $this->hasOne(PmsCounterReading::class, 'component_id')->latestOfMany('reading_date');
    }

    /** Ids of every component beneath this one, walked over an in-memory list of the vessel's components. */
    public function descendantIds(?Collection $all = null): array
    {
        $all ??= static::where('vessel_id', $this->vessel_id)->get(['id', 'parent_id']);
        $byParent = $all->groupBy('parent_id');

        $ids = [];
        $stack = [$this->id];
        while ($stack) {
            foreach ($byParent->get(array_pop($stack), []) as $child) {
                $ids[] = $child->id;
                $stack[] = $child->id;
            }
        }

        return $ids;
    }
}
