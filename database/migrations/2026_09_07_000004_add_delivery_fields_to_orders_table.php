<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_agent_id')) {
                $table->foreignId('delivery_agent_id')->nullable()->after('pickup_station_id')->constrained('delivery_agents')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'delivery_location_id')) {
                $table->foreignId('delivery_location_id')->nullable()->after('delivery_agent_id')->constrained('delivery_locations')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'delivery_charge_amount')) {
                $table->decimal('delivery_charge_amount', 10, 2)->nullable()->after('delivery_location_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_agent_id')) {
                $table->dropForeign(['delivery_agent_id']);
                $table->dropColumn('delivery_agent_id');
            }
            if (Schema::hasColumn('orders', 'delivery_location_id')) {
                $table->dropForeign(['delivery_location_id']);
                $table->dropColumn('delivery_location_id');
            }
            if (Schema::hasColumn('orders', 'delivery_charge_amount')) {
                $table->dropColumn('delivery_charge_amount');
            }
        });
    }
};
