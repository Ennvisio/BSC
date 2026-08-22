<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roles', function (Blueprint $table) {
         $table->increments('id');
         $table->integer('user_id');
         $table->integer('vessel_id')->nullable();
         $table->string('role');
         $table->string('created_by');
         $table->string('updated_by')->nullable();
         $table->string('user_type')->nullable();
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
        Schema::dropIfExists('roles');
    }
}
