<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('pickup_stations', function (Blueprint $table) {
            $table->decimal('pickup_shipping_fee', 10, 2)->nullable()->after('fee_pct');
        });
    }

    public function down()
    {
        Schema::table('pickup_stations', function (Blueprint $table) {
            $table->dropColumn('pickup_shipping_fee');
        });
    }
};
