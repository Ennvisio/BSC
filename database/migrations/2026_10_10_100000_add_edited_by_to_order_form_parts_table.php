<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks a correction made to an already-declared Part A after the ship
 * submitted it - Master fixing what Chief Officer sent, or Chief Engineer
 * fixing what Second Engineer sent, while it's still on their desk to
 * review (see Order::canEditPartA()). Kept separate from filled_by/
 * declaration, which stay as the ship's own original submission - an edit
 * here is a correction, not a re-declaration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_form_parts', function (Blueprint $table) {
            $table->unsignedInteger('edited_by')->nullable()->after('filled_by');
            $table->timestamp('edited_at')->nullable()->after('edited_by');

            $table->foreign('edited_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('order_form_parts', function (Blueprint $table) {
            $table->dropForeign(['edited_by']);
            $table->dropColumn(['edited_by', 'edited_at']);
        });
    }
};
