<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One historical snapshot of a Part A answer set, taken right before a
 * correction overwrote it - see OrderFormPartController::updatePartA().
 */
class OrderFormPartEdit extends Model
{
    protected $fillable = ['order_form_part_id', 'answers', 'edited_by', 'edited_at'];

    protected $casts = [
        'answers' => 'array',
        'edited_at' => 'datetime',
    ];

    public function formPart()
    {
        return $this->belongsTo(OrderFormPart::class, 'order_form_part_id');
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /** The answer set for one question key, same shape as OrderFormPart::answerFor() reads. */
    public function answerFor(string $key): array
    {
        $answer = $this->answers[$key] ?? ['choice' => null, 'followup' => null, 'fields' => []];

        if (is_array($answer['choice'] ?? null)) {
            $answer['choice'] = $answer['choice'][0] ?? null;
        }

        return $answer;
    }
}
