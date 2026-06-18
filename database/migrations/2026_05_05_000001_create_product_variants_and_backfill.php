<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the variants table if it does not already exist.
        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('name')->nullable(); // e.g. "Pink / 3-4"; auto-built from options
            $table->json('options')->nullable(); // {"Color":"Pink","Size":"3-4"}
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('discount', 5, 2)->default(0);
            $table->foreignId('image_id')->nullable()->constrained('product_images')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            });
        }

        // 2. Add nullable variant_id to dependent tables (will be backfilled then locked).
        if (! Schema::hasColumn('inventories', 'product_variant_id')) {
            try {
                Schema::table('inventories', function (Blueprint $table) {
                    $table->foreignId('product_variant_id')->nullable()->after('product_id');
                });
                // Add FK separately to catch constraint errors
                DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_product_variant_id_foreign FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE');
            } catch (\Throwable $e) {
                // If constraint already exists or cannot be added, continue
            }
        }

        if (! Schema::hasColumn('inventory_movements', 'product_variant_id')) {
            try {
                Schema::table('inventory_movements', function (Blueprint $table) {
                    $table->foreignId('product_variant_id')->nullable()->after('product_id');
                });
                DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_product_variant_id_foreign FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE');
            } catch (\Throwable $e) {
                // If constraint already exists or cannot be added, continue
            }
        }

        if (! Schema::hasColumn('purchase_items', 'product_variant_id')) {
            try {
                Schema::table('purchase_items', function (Blueprint $table) {
                    $table->foreignId('product_variant_id')->nullable()->after('product_id');
                });
                DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT purchase_items_product_variant_id_foreign FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE');
            } catch (\Throwable $e) {
                // If constraint already exists or cannot be added, continue
            }
        }

        if (! Schema::hasColumn('order_items', 'product_variant_id')) {
            try {
                Schema::table('order_items', function (Blueprint $table) {
                    $table->foreignId('product_variant_id')->nullable()->after('product_id');
                });
                DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_product_variant_id_foreign FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE');
            } catch (\Throwable $e) {
                // If constraint already exists or cannot be added, continue
            }
        }

        // 3. Backfill: create one "Default" variant per existing product, then point
        //    inventory/movements/purchase_items/order_items at that variant.
        DB::transaction(function () {
            $products = DB::table('products')->get();
            foreach ($products as $product) {
                // Build a unique variant SKU. Prefer product SKU, fall back to slug-based.
                $base = $product->sku ?: 'P'.$product->id;
                $variantSku = $base;
                $i = 1;
                while (DB::table('product_variants')->where('sku', $variantSku)->exists()) {
                    $variantSku = $base.'-V'.$i++;
                }

                $variantId = DB::table('product_variants')->insertGetId([
                    'product_id'    => $product->id,
                    'sku'           => $variantSku,
                    'name'          => 'Default',
                    'options'       => null,
                    'selling_price' => $product->selling_price ?? 0,
                    'discount'      => $product->discount ?? 0,
                    'image_id'      => null,
                    'is_active'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                DB::table('inventories')
                    ->where('product_id', $product->id)
                    ->update(['product_variant_id' => $variantId]);

                DB::table('inventory_movements')
                    ->where('product_id', $product->id)
                    ->update(['product_variant_id' => $variantId]);

                DB::table('purchase_items')
                    ->where('product_id', $product->id)
                    ->update(['product_variant_id' => $variantId]);

                DB::table('order_items')
                    ->where('product_id', $product->id)
                    ->update(['product_variant_id' => $variantId]);
            }
        });

        // 4. Drop the unique constraint on inventories.product_id so we can have
        //    one inventory row PER VARIANT (not per product). Must drop FK first!
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        try {
            // Drop FK on product_id if exists
            DB::statement('ALTER TABLE inventories DROP FOREIGN KEY inventories_product_id_foreign');
        } catch (\Throwable $_) {}

        try {
            // Drop unique index on product_id if exists
            if (Schema::hasTable('inventories')) {
                DB::statement('ALTER TABLE inventories DROP INDEX inventories_product_id_unique');
            }
        } catch (\Throwable $_) {}

        try {
            // Re-add product_id FK for referential integrity
            DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE');
        } catch (\Throwable $_) {}

        try {
            // Add unique constraint on product_variant_id
            DB::statement('ALTER TABLE inventories ADD UNIQUE KEY inventories_variant_unique (product_variant_id)');
        } catch (\Throwable $_) {}
        
        // Add indexes for query performance
        try {
            DB::statement('ALTER TABLE inventories ADD INDEX inventories_product_id_index (product_id)');
        } catch (\Throwable $_) {}
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique('inventories_variant_unique');
            $table->dropForeign(['product_id']);
            $table->dropIndex(['product_id']);
            $table->unique('product_id', 'inventories_product_id_unique');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });

        Schema::dropIfExists('product_variants');
    }
};
