<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Order items: quantity-based cancellation (no duplication of quantity)
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'cancelled_quantity')) {
                $table->unsignedInteger('cancelled_quantity')->default(0)->after('quantity');
            }
            if (! Schema::hasColumn('order_items', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('cancelled_quantity');
            }
        });

        // Refund requests: provider-neutral fields + processing timestamps
        Schema::table('refund_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('refund_requests', 'refund_processing_at')) {
                $table->dateTime('refund_processing_at')->nullable()->after('opay_payload');
            }
            if (! Schema::hasColumn('refund_requests', 'last_refund_check_at')) {
                $table->dateTime('last_refund_check_at')->nullable()->after('refund_processing_at');
            }
            if (! Schema::hasColumn('refund_requests', 'payment_provider')) {
                $table->string('payment_provider')->default('paystack')->after('last_refund_check_at');
            }
            if (! Schema::hasColumn('refund_requests', 'provider_refund_reference')) {
                $table->string('provider_refund_reference')->nullable()->after('payment_provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'cancelled_quantity')) {
                $table->dropColumn('cancelled_quantity');
            }
            if (Schema::hasColumn('order_items', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
        });

        Schema::table('refund_requests', function (Blueprint $table) {
            foreach (['refund_processing_at', 'last_refund_check_at', 'payment_provider', 'provider_refund_reference'] as $col) {
                if (Schema::hasColumn('refund_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
