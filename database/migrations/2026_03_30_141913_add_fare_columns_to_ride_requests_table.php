<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFareColumnsToRideRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            // Booking-time estimates
            $table->decimal('estimated_distance', 10, 2)->nullable()->after('distance');
            $table->decimal('estimated_duration', 10, 2)->nullable()->after('estimated_distance');
            $table->decimal('estimated_fare', 10, 2)->nullable()->after('estimated_duration');

            // Ride completion actuals
            $table->decimal('actual_distance', 10, 2)->nullable()->after('estimated_fare');
            $table->decimal('actual_duration', 10, 2)->nullable()->after('actual_distance');
            $table->decimal('actual_fare', 10, 2)->nullable()->after('actual_duration');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_distance',
                'estimated_duration',
                'estimated_fare',
                'actual_distance',
                'actual_duration',
                'actual_fare',
            ]);
        });
    }
}
