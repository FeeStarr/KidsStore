<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_out_for_delivery_at')) {
                $table->timestamp('delivery_out_for_delivery_at')->nullable()->after('delivery_received_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_out_for_delivery_at');
        });
    }
};
