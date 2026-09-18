<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_creations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image_path');
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('is_price_from')->default(false);
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('category');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_creations');
    }
};
