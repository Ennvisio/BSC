<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVesselsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vessels', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('owner_name');
            $table->text('owner_address');
            $table->string('manager_name');
            $table->text('manager_address');
            $table->string('master_name');
            $table->string('master_cert_no');
            $table->date('master_cert_validity');
            $table->string('ch_eng_name');
            $table->string('ch_eng_cert_no');
            $table->date('ch_eng_cert_validity');
            $table->string('prev_port_no')->nullable();
            $table->date('prev_reg_date')->nullable();
            $table->boolean('status')->default(false);
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
        Schema::dropIfExists('vessels');
    }
}
