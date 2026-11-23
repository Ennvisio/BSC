<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Files not hidden by their uploader (PD-61). A hidden file is kept,
     * with its record and its file on disk, but no longer offered in the
     * library or for linking.
     */
    public function scopeVisible($query)
    {
        return $query->whereNull($this->qualifyColumn('hidden_at'));
    }

    /**
     * PD-61: why this file may not be deleted, or null. A file attached to
     * a requisition line, to a procurement stage (a stage row exists only
     * once the stage is completed) or to a consumption record is evidence
     * and stays.
     */
    public function deletionRefusal(): ?string
    {
        $links = [
            'requisition item' => DB::table('attachment_order_item')->where('attachment_id', $this->id)->count(),
            'completed procurement stage' => DB::table('attachment_order_step')->where('attachment_id', $this->id)->count()
                + DB::table('attachment_service_req_step')->where('attachment_id', $this->id)->count(),
            'consumption record' => DB::table('attachment_stock_consumption')->where('attachment_id', $this->id)->count(),
        ];

        if ($links['requisition item'] > 0) {
            $n = $links['requisition item'];

            return "This file is attached to {$n} requisition item".($n === 1 ? '' : 's').' - remove it from those first.';
        }
        foreach (['completed procurement stage', 'consumption record'] as $kind) {
            if ($links[$kind] > 0) {
                return "This file is the record of a {$kind}, so it is kept.";
            }
        }

        return null;
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

    /**
     * Keeps the file's SHA-256 (Checkpoint 7: integrity checks and sync)
     * where the column exists; a database not yet migrated works as before.
     */
    public function recordChecksum(string $sha256): void
    {
        static $hasColumn = null;
        $hasColumn ??= Schema::hasColumn($this->getTable(), 'sha256');

        if ($hasColumn) {
            $this->sha256 = $sha256;
        }
    }

    /**
     * Headers for showing a stored file in the browser. nosniff keeps the
     * browser to the stored type; anything but a PDF (which the browser's
     * own viewer must be able to open) is also sandboxed, so a file can
     * never run script as this site.
     */
    public static function responseHeaders(string $mime): array
    {
        $headers = ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff'];
        if ($mime !== 'application/pdf') {
            $headers['Content-Security-Policy'] = "sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'";
        }

        return $headers;
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
