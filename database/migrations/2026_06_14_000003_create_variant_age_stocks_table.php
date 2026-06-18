<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('variant_age_stocks')) {
            return;
        }

        Schema::create('variant_age_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('selected_size', 64)->nullable();
            $table->string('age_group', 32);
            $table->integer('quantity')->default(0);
            $table->integer('reorder_level')->default(5);
            $table->timestamps();

            $table->unique(['product_variant_id', 'selected_size', 'age_group'], 'variant_age_stock_unique');
            $table->index(['product_variant_id', 'age_group'], 'variant_age_stock_variant_age_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_age_stocks');
    }
};
