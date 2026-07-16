<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->default(0)->after('discount');
        });

        // Seed from existing variant inventories
        DB::statement('
            UPDATE products p
            SET stock_quantity = (
                SELECT COALESCE(SUM(i.quantity), 0)
                FROM product_variants pv
                JOIN inventories i ON i.product_variant_id = pv.id
                WHERE pv.product_id = p.id
            )
        ');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('stock_quantity');
        });
    }
};
