<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAndroidAndIosUrlToAppSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('android_url')->nullable()->after('help_support_url');
            $table->string('ios_url')->nullable()->after('android_url');
            $table->string('driver_android_url')->nullable()->after('ios_url');
            $table->string('driver_ios_url')->nullable()->after('driver_android_url');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['android_url', 'ios_url','driver_android_url','driver_ios_url']);
        });
    }
}
