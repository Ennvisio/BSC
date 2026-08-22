<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFrameworkDescriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('framework_descriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vessel_id');
            $table->string('bulk_no')->nullable();
            $table->string('length_stem_rudder')->nullable();
            $table->string('main_breadth')->nullable();
            $table->string('dept_tonnag_ceil')->nullable();
            $table->string('shaft_no')->nullable();
            $table->string('eng_set_no')->nullable();
            $table->string('loaded_pressure')->nullable();
            $table->string('gro_ton')->nullable();
            $table->string('net_ton')->nullable();
            $table->string('cert_accom')->nullable();
            $table->string('lifeboat_num')->nullable();
            $table->string('rafts_num')->nullable();
            $table->string('per_accom_num')->nullable();
            $table->string('rafts_req_num')->nullable();
            $table->string('buoys_num')->nullable();
            $table->string('jack_num')->nullable();
            $table->string('imm_suit_num')->nullable();
            $table->string('therm_pro_num')->nullable();
            $table->string('trans_rud_num')->nullable();
            $table->string('propeller')->nullable();
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
        Schema::dropIfExists('framework_descriptions');
    }
}
