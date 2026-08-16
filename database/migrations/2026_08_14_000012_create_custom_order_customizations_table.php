<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_order_customizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_order_id')->constrained('custom_orders')->cascadeDelete();
            $table->string('attribute', 64); // dress_style, sleeve, neckline, skirt, length, waist, fabric, embellishment
            $table->string('value', 128);
            $table->timestamps();

            $table->unique(['custom_order_id', 'attribute']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_order_customizations');
    }
};
