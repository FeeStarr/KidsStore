<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->foreignId('delivery_location_id')->nullable()->after('pickup_station_id')
                ->constrained('delivery_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_location_id');
        });
    }
};
