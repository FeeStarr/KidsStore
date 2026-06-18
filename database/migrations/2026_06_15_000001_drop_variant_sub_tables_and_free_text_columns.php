<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the legacy sub-table approach (variant_sizes, variant_age_stocks) and the
 * free-text duplicate columns (color, size, age_group) from product_variants.
 *
 * Going forward each ProductVariant row IS the fully-specified combination:
 *   Product + Color (FK → colors) + AgeRange (FK → age_ranges) + Size (FK → sizes)
 * Stock is tracked via the Inventory row that belongs to each variant directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop variant_size_id FK + column from inventories (added by 2026_06_11 migration).
        if (Schema::hasColumn('inventories', 'variant_size_id')) {
            try {
                Schema::table('inventories', function (Blueprint $table) {
                    $table->dropForeign(['variant_size_id']);
                });
            } catch (\Throwable $e) {
                // FK may already be missing — continue.
            }
            Schema::table('inventories', function (Blueprint $table) {
                $table->dropColumn('variant_size_id');
            });
        }

        // 2. Drop the variant_sizes table (sub-size rows within a variant).
        Schema::dropIfExists('variant_sizes');

        // 3. Drop the variant_age_stocks table (per-age stock rows within a variant).
        Schema::dropIfExists('variant_age_stocks');

        // 4. Drop free-text duplicate columns from product_variants.
        Schema::table('product_variants', function (Blueprint $table) {
            $toDrop = [];
            if (Schema::hasColumn('product_variants', 'color'))     $toDrop[] = 'color';
            if (Schema::hasColumn('product_variants', 'size'))      $toDrop[] = 'size';
            if (Schema::hasColumn('product_variants', 'age_group')) $toDrop[] = 'age_group';
            if (! empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }

    public function down(): void
    {
        // Restore free-text columns on product_variants.
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'color')) {
                $table->string('color')->nullable()->after('name');
            }
            if (! Schema::hasColumn('product_variants', 'size')) {
                $table->string('size', 64)->nullable()->after('color');
            }
            if (! Schema::hasColumn('product_variants', 'age_group')) {
                $table->json('age_group')->nullable()->after('size');
            }
        });

        // Recreate variant_sizes.
        Schema::create('variant_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('size')->nullable();
            $table->string('sku')->nullable()->unique();
            $table->integer('quantity')->default(0);
            $table->integer('reorder_level')->default(5);
            $table->timestamps();
        });

        // Recreate variant_age_stocks.
        Schema::create('variant_age_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('selected_size')->nullable();
            $table->string('age_group')->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('reorder_level')->default(5);
            $table->timestamps();
        });

        // Restore variant_size_id on inventories.
        if (! Schema::hasColumn('inventories', 'variant_size_id')) {
            Schema::table('inventories', function (Blueprint $table) {
                $table->foreignId('variant_size_id')->nullable()->constrained('variant_sizes')->nullOnDelete();
            });
        }
    }
};
