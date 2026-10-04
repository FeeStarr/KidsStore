<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->whereNotNull('custom_order_id')
            ->where('payment_method', 'paystack')
            ->update(['payment_method' => 'pay_now']);
    }

    public function down(): void
    {
        DB::table('orders')
            ->whereNotNull('custom_order_id')
            ->where('payment_method', 'pay_now')
            ->update(['payment_method' => 'paystack']);
    }
};
