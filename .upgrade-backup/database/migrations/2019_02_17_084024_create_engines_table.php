<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEnginesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('engines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vessel_id');
            $table->string('manu_name')->nullable();
            $table->string('manu_address')->nullable();
            $table->string('type')->nullable();
            $table->string('mod_num')->nullable();
            $table->string('sets_no')->nullable();
            $table->string('no_cyl_set')->nullable();
            $table->string('diam_cyl')->nullable();
            $table->string('length_stroke')->nullable();
            $table->string('power_kw')->nullable();
            $table->string('rpm')->nullable();
            $table->string('speed')->nullable();
            $table->string('charger')->nullable();
            $table->string('fuel')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('engines');
    }
}
