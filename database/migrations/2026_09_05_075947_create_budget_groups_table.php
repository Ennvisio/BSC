<?php

use App\BudgetGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('budget_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->boolean('status')->default(true);
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        // The 18 budget lines from the reference screenshot - seeded here so
        // the requisition wizard has real data on first use. BudgetGroup has
        // no $fillable (matches Category's convention), so attributes are set
        // individually rather than mass-assigned.
        $names = [
            'BJOYJATRA', 'Conveyance', 'Deck store', 'Dunnage', 'Engine Spare',
            'Engine Store', 'Entertainment', 'Heavy Machinery Spares/Spares/Auxiliary Engine',
            'Heavy Oil/ Diesel/Gas oil', 'Lub Oil', 'Medical', 'Overtime',
            'PPR Honourium', 'Saloon Stores', 'Spares', 'Tank Cleaning',
            'Traveling', 'Victualing', 'Watchman',
        ];

        foreach ($names as $name) {
            $group = new BudgetGroup;
            $group->name = $name;
            $group->status = true;
            $group->created_by = 'system';
            $group->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_groups');
    }
};
