<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Booking extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bookings', function($table) {
            $table->bigIncrements('id');
            $table->string('customer_name');
            $table->string('country_code');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->tinyInteger('surfing_experience');
            $table->date('visit_date');
            $table->string('desired_board');
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
        Schema::drop('bookings');
    }
}
