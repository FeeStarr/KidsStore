<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_order_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_order_id')->constrained('custom_orders')->cascadeDelete();
            $table->string('measurement_type', 64);
            $table->decimal('measurement_value', 8, 2);
            $table->string('measurement_unit', 4); // cm or in
            $table->timestamps();

            $table->unique(['custom_order_id', 'measurement_type'], 'co_measurement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_order_measurements');
    }
};
