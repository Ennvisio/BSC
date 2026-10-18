<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One file in a user's personal library - uploaded once, independent of any
 * requisition, then linked to as many order lines as make sense via the
 * attachment_order_item pivot (see OrderItem::attachments()).
 */
class Attachment extends Model
{
    public const KINDS = ['image', 'pdf', 'doc'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function orderItems()
    {
        return $this->belongsToMany(OrderItem::class, 'attachment_order_item');
    }

    /** 'image' | 'pdf' | 'doc' from a real upload's mime type - unknown types are rejected before this is ever called. */
    public static function kindForMime(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if ($mime === 'application/pdf') {
            return 'pdf';
        }

        return 'doc';
    }

    public function humanFileSize(): string
    {
        $bytes = $this->file_size;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
