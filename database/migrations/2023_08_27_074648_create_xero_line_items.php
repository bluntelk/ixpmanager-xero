<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateXeroLineItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('xero_line_items', function (Blueprint $table) {
            $table->id();
            $table->integer('cust_id')->nullable();
            $table->string('local_service');
            $table->string('xero_service')->nullable();
            $table->timestamps();
            $table->foreign('cust_id')->references('id')->on('cust');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('xero_line_items');
    }
}
