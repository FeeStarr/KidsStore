<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'ordered_at')) {
                $table->timestamp('ordered_at')->nullable()->after('expected_delivery_date');
            }
            if (! Schema::hasColumn('orders', 'pending_confirmation_at')) {
                $table->timestamp('pending_confirmation_at')->nullable()->after('ordered_at');
            }
            if (! Schema::hasColumn('orders', 'pending_payment_at')) {
                $table->timestamp('pending_payment_at')->nullable()->after('pending_confirmation_at');
            }
            if (! Schema::hasColumn('orders', 'expired_at')) {
                $table->timestamp('expired_at')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('orders', 'pickup_window_expired_at')) {
                $table->timestamp('pickup_window_expired_at')->nullable()->after('expired_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'ordered_at', 'pending_confirmation_at', 'pending_payment_at',
                'expired_at', 'pickup_window_expired_at',
            ]);
        });
    }
};