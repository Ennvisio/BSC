<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderApprovalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_approvals', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id');
            $table->integer('cheif_ofcr_app')->nullable();
            $table->integer('master_app')->nullable();
            $table->integer('chief_eng_app')->nullable();
            $table->integer('ast_m_app')->nullable();
            $table->integer('agm_app')->nullable();
            $table->integer('gm_app')->nullable();
            $table->integer('dgm_app_ssm')->nullable();
            $table->integer('agm_app_ssm')->nullable();
            $table->integer('am_app_ssm')->nullable();
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
        Schema::dropIfExists('order_approvals');
    }
}
