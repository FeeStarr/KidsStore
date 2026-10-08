<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            // The coupons FK currently reuses the composite unique index below,
            // so a dedicated index must exist before it can be dropped.
            $table->index('coupon_id');
            $table->dropUnique(['coupon_id', 'customer_id']);
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            // Guest orders have no customer, but their coupon usage must still count.
            $table->unsignedBigInteger('customer_id')->nullable()->change();
            // Usages are recorded once per order; per-customer limits are
            // enforced at validation time by CouponService::validate().
            $table->unique(['coupon_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropUnique(['coupon_id', 'order_id']);
        });

        // customer_id stays nullable on rollback: converting it back to
        // NOT NULL would fail (and would need to discard guest usages).
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->unique(['coupon_id', 'customer_id']);
        });
    }
};
