<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTheaterPerformancesPivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('performance_theater', function (Blueprint $table) {
            $table->unsignedBigInteger('performance_id');
            $table->foreign('performance_id')->references('id')
                ->on('performances')->onDelete('cascade');

            $table->unsignedBigInteger('theater_id');
            $table->foreign('theater_id')->references('id')
                ->on('theaters')->onDelete('cascade');

            $table->string('seance_dt_list')->nullable()->default(null);
            $table->string('price')->nullable()->default(null);

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
        Schema::dropIfExists('theater_performances_pivot');
    }
}
