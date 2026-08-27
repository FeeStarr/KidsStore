<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_methods')->where('key', 'pay_now')->update(['is_active' => true]);
        DB::table('payment_methods')->where('key', 'pay_on_delivery')->update(['is_active' => true]);
        DB::table('payment_methods')->whereIn('key', ['instant_bank_transfer', 'pay_at_pickup', 'transfer'])->update(['is_active' => false]);
    }

    public function down(): void
    {
        DB::table('payment_methods')->where('key', 'instant_bank_transfer')->update(['is_active' => true]);
        DB::table('payment_methods')->where('key', 'pay_at_pickup')->update(['is_active' => true]);
        DB::table('payment_methods')->whereIn('key', ['pay_now', 'pay_on_delivery'])->update(['is_active' => false]);
    }
};
