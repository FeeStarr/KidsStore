<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('pickup_station_fee', 10, 2)->default(0)->after('line_total');
            $table->boolean('pickup_station_fee_paid')->default(false)->after('pickup_station_fee');
            $table->timestamp('pickup_station_fee_paid_at')->nullable()->after('pickup_station_fee_paid');
        });
    }

    public function down()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['pickup_station_fee', 'pickup_station_fee_paid', 'pickup_station_fee_paid_at']);
        });
    }
};
