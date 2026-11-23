<?php

use App\Sync\TriggerManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bookkeeping tables for cloud-vessel synchronisation (Checkpoint 2).
 * They are new and empty, so creating them cannot affect existing data, and
 * down() removes them completely, together with the capture triggers that
 * write into sync_outbox.
 *
 * - sync_nodes:   registered servers (the cloud and each vessel) and their
 *                 hashed access tokens.
 * - sync_outbox:  every local change, written by database triggers in the
 *                 same transaction as the change.
 * - sync_inbox:   every change received from another node. event_id is
 *                 unique, so the same event can never be stored twice.
 * - sync_cursors: how far each peer has got through a stream of events.
 * - sync_logs:    one row per sync action, for operators.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_nodes', function (Blueprint $table) {
            $table->id();
            $table->char('node_id', 36)->unique();
            $table->string('kind', 16);                     // cloud | vessel
            $table->unsignedInteger('vessel_id')->nullable()->index();
            $table->string('name');
            $table->char('token_hash', 64)->nullable()->unique();
            $table->string('status', 16)->default('active'); // active | revoked
            $table->string('app_version', 32)->nullable();
            $table->string('schema_version', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_outbox', function (Blueprint $table) {
            $table->bigIncrements('id');                    // local order of events
            $table->char('event_id', 36)->unique();         // global identity of the event
            $table->string('table_name', 64);
            $table->string('row_id', 36);                   // the row's primary key on this server
            $table->char('row_uuid', 36)->nullable()->index();
            $table->string('operation', 8);                 // insert | update | delete
            $table->unsignedInteger('row_version')->nullable();
            $table->unsignedInteger('vessel_id')->nullable(); // null = fleet-wide
            $table->char('origin_node', 36)->nullable();    // null = changed on this server
            $table->timestamp('created_at', 6)->nullable();

            $table->index(['vessel_id', 'id']);
            $table->index(['table_name', 'id']);
        });

        Schema::create('sync_inbox', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('event_id', 36)->unique();         // duplicate deliveries are refused
            $table->char('source_node', 36)->index();
            $table->string('table_name', 64);
            $table->char('row_uuid', 36)->index();
            $table->string('operation', 8);
            $table->unsignedInteger('row_version')->nullable();
            $table->unsignedInteger('vessel_id')->nullable();
            $table->longText('payload')->nullable();        // the row as JSON
            $table->string('status', 16)->default('pending')->index(); // pending | applied | deferred | failed | skipped
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('received_at', 6)->nullable();
            $table->timestamp('applied_at')->nullable();
        });

        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->char('node_id', 36);                    // the peer this cursor tracks
            $table->string('stream', 32);                   // e.g. push, pull
            $table->unsignedBigInteger('position')->default(0);
            $table->char('last_event_id', 36)->nullable();
            $table->timestamps();

            $table->unique(['node_id', 'stream']);
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('node_id', 36)->nullable();
            $table->string('action', 32);                   // e.g. handshake, push, pull, install-triggers
            $table->string('status', 16);                   // ok | warning | error
            $table->unsignedInteger('events')->default(0);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('message')->nullable();
            $table->longText('context')->nullable();        // JSON
            $table->timestamp('created_at', 6)->nullable();

            $table->index(['node_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        // The capture triggers write into sync_outbox. Left in place, they
        // would make every write to the application tables fail.
        app(TriggerManager::class)->drop();

        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('sync_cursors');
        Schema::dropIfExists('sync_inbox');
        Schema::dropIfExists('sync_outbox');
        Schema::dropIfExists('sync_nodes');
    }
};
