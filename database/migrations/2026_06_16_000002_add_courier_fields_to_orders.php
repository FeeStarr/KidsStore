<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('courier_name')->nullable()->after('delivery_address')
                ->comment('Company or individual handling the delivery');
            $table->string('tracking_number')->nullable()->after('courier_name')
                ->comment('Courier tracking reference');
            $table->string('tracking_url')->nullable()->after('tracking_number')
                ->comment('Optional direct tracking link');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['courier_name', 'tracking_number', 'tracking_url']);
        });
    }
};
