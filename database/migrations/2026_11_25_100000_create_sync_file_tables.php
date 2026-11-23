<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files that travel with synchronised records (Checkpoint 7). Additive
 * only: one new nullable column and two new, empty tables.
 *
 * - attachments.sha256: the file's SHA-256, recorded when it is uploaded,
 *                       so its integrity can be checked on every server.
 *                       Existing rows get it from "php artisan
 *                       sync:files checksum" (it reads the files only).
 * - sync_files:         per file a record refers to (table, row, column),
 *                       what this server knows: its path, checksum and size,
 *                       and whether it is here, wanted from a peer, or
 *                       failed. Sync bookkeeping, never synchronised itself.
 * - sync_file_grants:   cloud only: the records whose files a vessel may
 *                       download, because the cloud sent it those records.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attachments') && ! Schema::hasColumn('attachments', 'sha256')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->char('sha256', 64)->nullable()->after('file_size');
                $table->index('sha256');
            });
        }

        Schema::create('sync_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('table_name', 64);
            $table->char('row_uuid', 36);
            $table->string('column_name', 64);
            $table->string('path', 500);
            $table->char('sha256', 64)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            // present | wanted | failed | missing (gone from this server)
            $table->string('state', 16);
            $table->char('peer_node', 36)->nullable();   // who sends it, while wanted
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['table_name', 'row_uuid', 'column_name'], 'sync_files_ref_unique');
            $table->index(['state', 'peer_node'], 'sync_files_state_index');
            $table->index('sha256');
        });

        Schema::create('sync_file_grants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('vessel_id');
            $table->string('table_name', 64);
            $table->char('row_uuid', 36);
            $table->timestamp('created_at')->nullable();

            $table->unique(['vessel_id', 'table_name', 'row_uuid'], 'sync_file_grants_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_file_grants');
        Schema::dropIfExists('sync_files');
        if (Schema::hasTable('attachments') && Schema::hasColumn('attachments', 'sha256')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->dropIndex(['sha256']);
                $table->dropColumn('sha256');
            });
        }
    }
};
