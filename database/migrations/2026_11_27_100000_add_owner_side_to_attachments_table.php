<?php

use App\Sync\SyncContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PD-59 B (approved 2026-11-24): an attachment belongs to the side that
 * uploaded it. 'vessel' for a file uploaded by the vessel's crew (on board,
 * or on the cloud before the vessel had its own server), 'cloud' for one
 * uploaded in the office. Set once, when the record is created
 * (App\Attachment), and never changed.
 *
 * Existing records are filled in from the uploader's role: a ship role
 * means the vessel uploaded it. Additive only: one nullable column. The
 * capture triggers are reinstalled afterwards by `sync:triggers install`,
 * as after every schema change to a synchronised table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attachments')) {
            return; // partial schemas (tests)
        }
        if (! Schema::hasColumn('attachments', 'owner_side')) {
            Schema::table('attachments', fn (Blueprint $t) => $t->string('owner_side', 10)->nullable());
        }

        $hasRoles = Schema::hasTable('roles') && Schema::hasColumn('roles', 'user_type');
        $vessel = $hasRoles
            ? "EXISTS (SELECT 1 FROM roles WHERE roles.user_id = attachments.uploaded_by AND roles.user_type = 'ship')"
            : '1 = 0';

        // Recording who uploaded a file is not a change to the data.
        SyncContext::withoutCapture(fn () => DB::table('attachments')->whereNull('owner_side')
            ->update(['owner_side' => DB::raw("CASE WHEN {$vessel} THEN 'vessel' ELSE 'cloud' END")]));
    }

    public function down(): void
    {
        if (Schema::hasTable('attachments') && Schema::hasColumn('attachments', 'owner_side')) {
            Schema::table('attachments', fn (Blueprint $t) => $t->dropColumn('owner_side'));
        }
    }
};
