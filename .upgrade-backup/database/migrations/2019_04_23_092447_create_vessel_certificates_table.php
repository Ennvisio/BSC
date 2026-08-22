<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVesselCertificatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vessel_certificates', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('certificate_id');
            $table->integer('vessel_id');
            $table->string('issue_auth');
            $table->date('issue_date');
            $table->date('exp_date');
            $table->string('cert_copy');
            $table->boolean('status')->default(true);
            $table->string('created_by');
            $table->string('updated_by')->nullable();
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
        Schema::dropIfExists('vessel_certificates');
    }
}
