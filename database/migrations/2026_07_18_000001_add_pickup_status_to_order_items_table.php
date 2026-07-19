<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'pickup_status')) {
                $table->enum('pickup_status', ['pending', 'received', 'ready for pickup', 'picked_up'])
                    ->default('pending')
                    ->after('pickup_station_fee_paid_at');
            }
            if (! Schema::hasColumn('order_items', 'pickup_status_changed_at')) {
                $table->timestamp('pickup_status_changed_at')->nullable()->after('pickup_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['pickup_status', 'pickup_status_changed_at']);
        });
    }
};
