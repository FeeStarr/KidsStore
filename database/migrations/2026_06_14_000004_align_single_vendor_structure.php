<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createLookupTables();
        $this->addProductColumns();
        $this->addVariantColumns();
        $this->addInventoryColumns();
        $this->addPurchaseColumns();
        $this->addOrderColumns();
        $this->upgradeInventoryMovementTypes();
        $this->backfillLookupReferences();
    }

    public function down(): void
    {
        // Intentional no-op down migration to avoid destructive rollback on production data.
    }

    private function createLookupTables(): void
    {
        if (! Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('age_ranges')) {
            Schema::create('age_ranges', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sizes')) {
            Schema::create('sizes', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('colors')) {
            Schema::create('colors', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    private function addProductColumns(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->after('category_id')->constrained('brands')->nullOnDelete();
            }
            if (! Schema::hasColumn('products', 'image')) {
                $table->string('image')->nullable()->after('description');
            }
            if (! Schema::hasColumn('products', 'status')) {
                $table->string('status', 24)->default('active')->after('image');
            }
        });
    }

    private function addVariantColumns(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'age_range_id')) {
                $table->foreignId('age_range_id')->nullable()->after('sku')->constrained('age_ranges')->nullOnDelete();
            }
            if (! Schema::hasColumn('product_variants', 'size_id')) {
                $table->foreignId('size_id')->nullable()->after('age_range_id')->constrained('sizes')->nullOnDelete();
            }
            if (! Schema::hasColumn('product_variants', 'color_id')) {
                $table->foreignId('color_id')->nullable()->after('size_id')->constrained('colors')->nullOnDelete();
            }
        });
    }

    private function addInventoryColumns(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('inventories', 'quantity_on_hand')) {
                $table->integer('quantity_on_hand')->default(0)->after('quantity');
            }
        });
    }

    private function addPurchaseColumns(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'purchase_number')) {
                $table->string('purchase_number')->nullable()->after('id');
                $table->unique('purchase_number', 'purchases_purchase_number_unique');
            }
            if (! Schema::hasColumn('purchases', 'total_cost')) {
                $table->decimal('total_cost', 14, 2)->default(0)->after('purchase_number');
            }
        });
    }

    private function addOrderColumns(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'total_amount')) {
                $table->decimal('total_amount', 14, 2)->default(0)->after('customer_id');
            }
        });
    }

    private function upgradeInventoryMovementTypes(): void
    {
        if (! Schema::hasTable('inventory_movements')) {
            return;
        }

        try {
            DB::statement("UPDATE inventory_movements SET type = 'sale' WHERE type = 'order'");
            DB::statement("ALTER TABLE inventory_movements MODIFY COLUMN type ENUM('purchase','sale','return','damage','adjustment')");
        } catch (\Throwable $e) {
            // Keep migration resilient in environments with a different engine/schema state.
        }
    }

    private function backfillLookupReferences(): void
    {
        $this->seedStaticAgeRanges();

        $brands = DB::table('products')->whereNotNull('brand')->where('brand', '!=', '')->distinct()->pluck('brand');
        foreach ($brands as $brand) {
            DB::table('brands')->updateOrInsert(['name' => trim((string) $brand)], ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]);
        }

        // Only backfill sizes if the column exists (from 2026_06_14_000001)
        if (Schema::hasColumn('product_variants', 'size')) {
            $sizes = DB::table('product_variants')->whereNotNull('size')->where('size', '!=', '')->distinct()->pluck('size');
            foreach ($sizes as $size) {
                DB::table('sizes')->updateOrInsert(['name' => trim((string) $size)], ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]);
            }
        }

        // Only backfill colors if the column exists (from 2026_06_14_000001)
        if (Schema::hasColumn('product_variants', 'color')) {
            $colors = DB::table('product_variants')->whereNotNull('color')->where('color', '!=', '')->distinct()->pluck('color');
            foreach ($colors as $color) {
                DB::table('colors')->updateOrInsert(['name' => trim((string) $color)], ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]);
            }
        }

        DB::statement("UPDATE inventories SET quantity_on_hand = COALESCE(quantity, 0) WHERE quantity_on_hand = 0");
        DB::statement("UPDATE purchases SET purchase_number = reference WHERE purchase_number IS NULL OR purchase_number = ''");
        DB::statement("UPDATE purchases SET total_cost = COALESCE(grand_total, 0) WHERE total_cost = 0");
        DB::statement("UPDATE orders SET total_amount = COALESCE(grand_total, 0) WHERE total_amount = 0");

        DB::statement(
            "UPDATE products p
             LEFT JOIN brands b ON b.name = p.brand
             SET p.brand_id = b.id
             WHERE p.brand_id IS NULL AND p.brand IS NOT NULL AND p.brand <> ''"
        );

        DB::statement(
            "UPDATE products SET status = CASE
                WHEN is_active = 1 THEN 'active'
                ELSE 'inactive'
            END
            WHERE status IS NULL OR status = ''"
        );

        // Only update size_id if the size column exists
        if (Schema::hasColumn('product_variants', 'size')) {
            DB::statement(
                "UPDATE product_variants pv
                 LEFT JOIN sizes s ON s.name = pv.size
                 SET pv.size_id = s.id
                 WHERE pv.size_id IS NULL AND pv.size IS NOT NULL AND pv.size <> ''"
            );
        }

        // Only update color_id if the color column exists
        if (Schema::hasColumn('product_variants', 'color')) {
            DB::statement(
                "UPDATE product_variants pv
                 LEFT JOIN colors c ON c.name = pv.color
                 SET pv.color_id = c.id
                 WHERE pv.color_id IS NULL AND pv.color IS NOT NULL AND pv.color <> ''"
            );
        }

        // Only process age_group if the column exists
        if (Schema::hasColumn('product_variants', 'age_group')) {
            $variants = DB::table('product_variants')->select('id', 'age_group')->get();
            foreach ($variants as $variant) {
                if (empty($variant->age_group)) {
                    continue;
                }
                $decoded = json_decode((string) $variant->age_group, true);
                if (! is_array($decoded) || empty($decoded[0])) {
                    continue;
                }
                $first = trim((string) $decoded[0]);
                if ($first === '') {
                    continue;
                }
                $age = DB::table('age_ranges')->where('name', $first)->first();
                if ($age) {
                    DB::table('product_variants')->where('id', $variant->id)->whereNull('age_range_id')->update(['age_range_id' => $age->id]);
                }
            }
        }
    }

    private function seedStaticAgeRanges(): void
    {
        $defaults = [
            '0-3 months',
            '3-6 months',
            '6-9 months',
            '9-12 months',
            '12-18 months',
            '18-24 months',
            '2-3 years',
            '3-4 years',
            '4-5 years',
            '5-6 years',
            '6-7 years',
            '7-8 years',
            '8-9 years',
            '9-10 years',
            '10-11 years',
            '11-12 years',
            '12-13 years',
            '13-14 years',
            '14-15 years',
            '15-16 years',
        ];

        foreach ($defaults as $name) {
            DB::table('age_ranges')->updateOrInsert(['name' => $name], ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]);
        }
    }
};
