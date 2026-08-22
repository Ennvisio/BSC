<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVesselSurveysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vessel_surveys', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vessel_id');
            $table->integer('survey_id');
            $table->string('society_name');
            $table->date('survey_date');
            $table->date('survey_exp_date');
            $table->boolean('status')->default(true);     
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
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
        Schema::dropIfExists('vessel_surveys');
    }
}
