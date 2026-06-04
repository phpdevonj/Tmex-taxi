<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStripeAccountIdAndPayoutScheduleToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('stripe_customer_id');
            $table->string('payout_schedule')->default('weekly')->after('stripe_account_id'); // e.g., 'daily', 'weekly', 'monthly'
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['stripe_account_id', 'payout_schedule']);
        });
    }
}
