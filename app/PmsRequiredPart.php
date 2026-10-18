<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** A part a job needs - the definition's template row (job_definition_id set) or a job's own copy (job_id set). */
class PmsRequiredPart extends Model
{
    protected $fillable = [
        'job_definition_id', 'job_id', 'item_id', 'part_name', 'article_no', 'part_no', 'drawing_no',
        'manufacturer_id', 'quantity', 'unit', 'est_delivery_weeks', 'est_price', 'order_id',
    ];

    protected $casts = ['quantity' => 'integer', 'est_delivery_weeks' => 'integer', 'est_price' => 'decimal:2'];

    /** Validation rules for a part's form fields; $prefix is e.g. 'parts.*.' for a list of them. */
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'item_id' => ['nullable', 'integer', 'exists:items,id'],
            $prefix.'part_name' => ['required_without:'.$prefix.'item_id', 'nullable', 'string', 'max:255'],
            $prefix.'article_no' => ['nullable', 'string', 'max:255'],
            $prefix.'part_no' => ['nullable', 'string', 'max:255'],
            $prefix.'drawing_no' => ['nullable', 'string', 'max:255'],
            $prefix.'manufacturer_id' => ['nullable', 'integer', 'exists:manufacturers,id'],
            $prefix.'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            $prefix.'unit' => ['required_without:'.$prefix.'item_id', 'nullable', 'string', 'max:50'],
            $prefix.'est_delivery_weeks' => ['nullable', 'integer', 'min:0', 'max:999'],
            $prefix.'est_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    /** Give a newly generated job its own copy of the definition's parts, free of any requisition. */
    public static function copyTemplateTo(PmsJobDefinition $definition, PmsJob $job): void
    {
        foreach ($definition->requiredParts as $part) {
            static::create(array_merge(
                collect($part->getAttributes())->except(['id', 'created_at', 'updated_at'])->all(),
                ['job_id' => $job->id, 'job_definition_id' => null, 'order_id' => null]
            ));
        }
    }

    public function definition()
    {
        return $this->belongsTo(PmsJobDefinition::class, 'job_definition_id')->withTrashed();
    }

    public function job()
    {
        return $this->belongsTo(PmsJob::class, 'job_id');
    }

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
