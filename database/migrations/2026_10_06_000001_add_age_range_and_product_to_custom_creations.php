<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_creations', function (Blueprint $table) {
            $table->string('age_range')->nullable()->after('category');
            $table->foreignId('product_id')
                ->nullable()
                ->after('age_range')
                ->constrained('products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_creations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn('age_range');
        });
    }
};
