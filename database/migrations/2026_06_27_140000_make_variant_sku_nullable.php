<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_variants') && Schema::hasColumn('product_variants', 'sku')) {
            // Modify column to allow NULL. Use raw statement to avoid doctrine/dbal dependency.
            DB::statement("ALTER TABLE `product_variants` MODIFY `sku` VARCHAR(64) NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_variants') && Schema::hasColumn('product_variants', 'sku')) {
            DB::statement("ALTER TABLE `product_variants` MODIFY `sku` VARCHAR(64) NOT NULL");
        }
    }
};
