<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSsnFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('ssn')->nullable()->after('device_type');
            $table->string('ssn_last_four', 4)->nullable()->after('ssn');
            $table->string('ssn_status', 20)->nullable()->after('ssn_last_four');
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
            $table->dropColumn(['ssn', 'ssn_last_four', 'ssn_status']);
        });
    }
}
