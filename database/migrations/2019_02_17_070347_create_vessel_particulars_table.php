<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVesselParticularsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vessel_particulars', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vessel_id');
            $table->string('type')->nullable();
            $table->string('flag')->nullable();
            $table->string('call_sign')->nullable();
            $table->string('imo_no')->nullable();
            $table->string('grt')->nullable();
            $table->string('nrt')->nullable();
            $table->string('dwt')->nullable();
            $table->string('off_no')->nullable();
            $table->date('keel_lay_date')->nullable();
            $table->date('launch_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->date('cert_date')->nullable();
            $table->string('built_year')->nullable();
            $table->string('built_loc')->nullable();
            $table->string('steam_motor_propelled')->nullable();
            $table->string('builder_name')->nullable();
            $table->string('builder_address')->nullable();
            $table->string('deck_no')->nullable();
            $table->string('mast_no')->nullable();
            $table->string('rigged')->nullable();
            $table->string('stem')->nullable();
            $table->string('stern')->nullable();
            $table->string('build')->nullable();
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
        Schema::dropIfExists('vessel_particulars');
    }
}
