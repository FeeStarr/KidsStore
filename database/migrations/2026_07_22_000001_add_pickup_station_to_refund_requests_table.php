<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->foreignId('pickup_station_id')->nullable()->after('order_item_id')->constrained()->nullOnDelete();
            $table->timestamp('return_collected_at')->nullable()->after('inspected_at');
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropForeign(['pickup_station_id']);
            $table->dropColumn(['pickup_station_id', 'return_collected_at']);
        });
    }
};
