<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vessel_id');
            $table->integer('category_id');
            $table->string('req_no')->unique();
            $table->date('req_date');
            $table->string('port_name');
            $table->string('status')->default('on process');
            $table->string('status_from_am')->nullable();
            $table->date('deliver_date')->nullable();
            $table->date('rcv_date')->nullable();
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->boolean('ord_status')->default(false);
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
        Schema::dropIfExists('orders');
    }
}
