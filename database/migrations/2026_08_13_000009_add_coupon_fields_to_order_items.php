<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->after('deal_id')->constrained('coupons')->nullOnDelete();
            }
            if (! Schema::hasColumn('order_items', 'coupon_discount')) {
                $table->decimal('coupon_discount', 12, 2)->default(0)->after('coupon_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'coupon_id')) {
                $table->dropForeign(['coupon_id']);
                $table->dropColumn('coupon_id');
            }
            if (Schema::hasColumn('order_items', 'coupon_discount')) {
                $table->dropColumn('coupon_discount');
            }
        });
    }
};