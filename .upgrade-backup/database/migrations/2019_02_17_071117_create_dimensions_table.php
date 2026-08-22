<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDimensionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dimensions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vessel_id');
            $table->string('length_LL')->nullable();
            $table->string('length_OA')->nullable();
            $table->string('breadth')->nullable();
            $table->string('depth')->nullable();
            $table->string('length_eng_room')->nullable();
            $table->string('draft')->nullable();
            $table->string('suez_geo_ton')->nullable();
            $table->string('suez_net_ton')->nullable();
            $table->string('pana_ton')->nullable();
            $table->string('class')->nullable();
            $table->string('class_not')->nullable();
            $table->string('hp')->nullable();
            $table->string('spreed')->nullable();
            $table->string('hold_cap')->nullable();
            $table->string('car_gear')->nullable();
            $table->string('car_hold')->nullable();
            $table->string('bunk_cap')->nullable();
            $table->string('ball_cap')->nullable();
            $table->string('water_cap')->nullable();
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
        Schema::dropIfExists('dimensions');
    }
}
