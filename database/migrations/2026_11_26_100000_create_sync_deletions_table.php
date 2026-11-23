<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkpoint 14 (ADR-003 deletion safety, ADR-006). Additive only.
 *
 * - sync_deletions: the lasting record that a row was deleted on this
 *   server, by whom and when. Written by the capture trigger in the same
 *   transaction as the delete, so the two always exist together. Unlike
 *   the outbox it is never pruned: it is how sync knows, months later, that
 *   a change arriving for this row must not bring it back.
 * - actor_uuid on sync_outbox and sync_inbox: the user who made the change
 *   (their users.sync_uuid), carried with the event so the receiving side
 *   records the same person. Null for changes made by the system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_deletions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('table_name', 64);
            $table->string('row_id', 36);                   // the row's primary key on this server
            $table->char('row_uuid', 36)->nullable();
            $table->unsignedInteger('vessel_id')->nullable();
            $table->char('actor_uuid', 36)->nullable();     // who deleted it; null = the system
            $table->char('origin_node', 36)->nullable();    // null = deleted on this server
            $table->timestamp('deleted_at', 6)->nullable();

            $table->index(['table_name', 'row_uuid']);
        });

        foreach (['sync_outbox', 'sync_inbox'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->char('actor_uuid', 36)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['sync_outbox', 'sync_inbox'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('actor_uuid');
            });
        }
        Schema::dropIfExists('sync_deletions');
    }
};
