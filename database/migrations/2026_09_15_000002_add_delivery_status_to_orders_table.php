<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_status')) {
                $table->string('delivery_status', 30)->nullable()->after('delivery_charge_amount');
            }
            if (! Schema::hasColumn('orders', 'delivery_released_at')) {
                $table->timestamp('delivery_released_at')->nullable();
            }
            if (! Schema::hasColumn('orders', 'delivery_received_at')) {
                $table->timestamp('delivery_received_at')->nullable();
            }
            if (! Schema::hasColumn('orders', 'delivery_delivered_at')) {
                $table->timestamp('delivery_delivered_at')->nullable();
            }
            if (! Schema::hasColumn('orders', 'delivery_issue_reason')) {
                $table->string('delivery_issue_reason', 50)->nullable();
            }
            if (! Schema::hasColumn('orders', 'delivery_issue_notes')) {
                $table->text('delivery_issue_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'delivery_status', 'delivery_released_at', 'delivery_received_at',
                'delivery_delivered_at', 'delivery_issue_reason', 'delivery_issue_notes',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
