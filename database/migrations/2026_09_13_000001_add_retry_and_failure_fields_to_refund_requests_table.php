<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->unsignedInteger('retry_count')->default(0)->after('provider_refund_reference');
            $table->text('failure_reason')->nullable()->after('retry_count');
            $table->dateTime('last_retry_at')->nullable()->after('failure_reason');
            $table->dateTime('processed_at')->nullable()->after('last_retry_at');
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropColumn(['retry_count', 'failure_reason', 'processed_at']);
        });
    }
};
