<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'deal_id')) {
                $table->foreignId('deal_id')->nullable()->after('discount')->constrained('deals')->nullOnDelete();
            }
            if (! Schema::hasColumn('order_items', 'original_unit_price')) {
                $table->decimal('original_unit_price', 12, 2)->nullable()->after('unit_price');
            }
            if (! Schema::hasColumn('order_items', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'deal_id')) {
                $table->dropForeign(['deal_id']);
                $table->dropColumn('deal_id');
            }
            if (Schema::hasColumn('order_items', 'original_unit_price')) {
                $table->dropColumn('original_unit_price');
            }
            if (Schema::hasColumn('order_items', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
        });
    }
};
