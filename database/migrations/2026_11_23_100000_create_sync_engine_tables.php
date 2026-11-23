<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables for the sync engine (Checkpoint 5). Everything here is new and
 * empty: no existing table or row is changed apart from two new nullable
 * columns on the Checkpoint 2 sync tables.
 *
 * - sync_outbox.seq:  the order in which events became visible (committed).
 *                     Cursors use it, so an event whose transaction commits
 *                     late can never be skipped.
 * - sync_inbox.batch_id: which push batch or pull page delivered an event.
 * - sync_batches:     push batches, kept by the sender until acknowledged
 *                     and by the receiver with the acknowledgement it gave,
 *                     so a repeated batch gets the same answer.
 * - sync_conflicts:   every conflict found, with both versions and the
 *                     outcome.
 * - sync_state:       small key/value store: worker state, the cloud's
 *                     identity as seen by a vessel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sync_outbox', 'seq')) {
            Schema::table('sync_outbox', function (Blueprint $table) {
                $table->unsignedBigInteger('seq')->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('sync_inbox', 'batch_id')) {
            Schema::table('sync_inbox', function (Blueprint $table) {
                $table->char('batch_id', 36)->nullable()->index();
            });
        }

        Schema::create('sync_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('batch_id', 36);
            $table->string('direction', 8);                 // out | in
            $table->char('node_id', 36)->index();           // the peer
            $table->unsignedBigInteger('seq_from');
            $table->unsignedBigInteger('seq_to');
            $table->unsignedInteger('events')->default(0);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->char('sha256', 64)->nullable();
            $table->longText('body')->nullable();           // out: the request, sent again unchanged on retry
            $table->longText('response')->nullable();       // in: the acknowledgement given
            $table->string('status', 16)->index();          // out: pending | acked;  in: applied
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'direction']);
        });

        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('event_id', 36)->nullable()->index();
            $table->char('source_node', 36)->nullable();
            $table->string('detected_by', 8);               // cloud | vessel
            $table->string('table_name', 64);
            $table->char('row_uuid', 36)->index();
            $table->string('kind', 24);                     // update_update | update_delete | delete_update | duplicate_key
            $table->string('policy', 8)->nullable();        // cloud | vessel | latest
            $table->string('resolution', 16);               // kept_local | took_remote | needs_review
            $table->unsignedInteger('local_version')->nullable();
            $table->unsignedInteger('remote_version')->nullable();
            $table->longText('local_values')->nullable();   // JSON
            $table->longText('remote_values')->nullable();  // JSON
            $table->string('status', 16)->default('resolved')->index(); // resolved | needs_review | reviewed
            $table->text('note')->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
        });

        Schema::create('sync_state', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_state');
        Schema::dropIfExists('sync_conflicts');
        Schema::dropIfExists('sync_batches');

        if (Schema::hasColumn('sync_inbox', 'batch_id')) {
            Schema::table('sync_inbox', function (Blueprint $table) {
                $table->dropIndex(['batch_id']);
                $table->dropColumn('batch_id');
            });
        }

        if (Schema::hasColumn('sync_outbox', 'seq')) {
            Schema::table('sync_outbox', function (Blueprint $table) {
                $table->dropUnique(['seq']);
                $table->dropColumn('seq');
            });
        }
    }
};
