<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'placed_notified_at')) {
                // Marks the single "order placed" email for this order as sent,
                // so placement + a later confirm() can never notify twice.
                $table->timestamp('placed_notified_at')->nullable()->after('confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'placed_notified_at')) {
                $table->dropColumn('placed_notified_at');
            }
        });
    }
};
