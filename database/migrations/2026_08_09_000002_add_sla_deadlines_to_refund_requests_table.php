<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->timestamp('review_deadline')->nullable()->after('reviewed_at');
            $table->timestamp('inspection_deadline')->nullable()->after('inspected_at');
            $table->timestamp('dropoff_deadline')->nullable()->after('return_collected_at');
            $table->boolean('review_sla_breached')->default(false)->after('review_deadline');
            $table->boolean('inspection_sla_breached')->default(false)->after('inspection_deadline');
            $table->boolean('dropoff_sla_breached')->default(false)->after('dropoff_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropColumn([
                'review_deadline', 'review_sla_breached',
                'inspection_deadline', 'inspection_sla_breached',
                'dropoff_deadline', 'dropoff_sla_breached',
            ]);
        });
    }
};
