<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class AddPickupFeeTotalToOrders extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'pickup_station_fee_total')) {
                $table->decimal('pickup_station_fee_total', 10, 2)->default(0)->after('shipping_fee');
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'pickup_station_fee_total')) {
                $table->dropColumn('pickup_station_fee_total');
            }
        });
    }
}
