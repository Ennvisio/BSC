<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookkeeping for merging business records (Checkpoint 6). Both tables are
 * new and empty; nothing existing is changed.
 *
 * - sync_row_bases: per peer and row, the values both servers last agreed
 *                   on. When both sides changed a requisition before seeing
 *                   each other's change, comparing with this tells which
 *                   side changed which column, so only columns changed on
 *                   both sides count as a conflict. "offered" values were
 *                   sent but not yet confirmed by the peer.
 * - sync_aliases:   two servers created the same record independently (the
 *                   same item linked to the same vessel on board and in the
 *                   office). The second copy is merged into the first; its
 *                   UUID is kept here as an alias so references to it still
 *                   resolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_row_bases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('peer_node', 36);
            $table->string('table_name', 64);
            $table->char('row_uuid', 36);
            $table->longText('base_values')->nullable();
            $table->longText('offered_values')->nullable();
            $table->unsignedBigInteger('offered_seq')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['peer_node', 'table_name', 'row_uuid']);
            $table->index(['peer_node', 'offered_seq']);
        });

        Schema::create('sync_aliases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('table_name', 64);
            $table->char('alias_uuid', 36);
            $table->char('target_uuid', 36);
            $table->char('source_node', 36)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['table_name', 'alias_uuid']);
            $table->index(['table_name', 'target_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_aliases');
        Schema::dropIfExists('sync_row_bases');
    }
};
