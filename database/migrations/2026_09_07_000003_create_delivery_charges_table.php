<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained('delivery_agents')->cascadeOnDelete();
            $table->foreignId('delivery_location_id')->constrained('delivery_locations')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->timestamps();

            $table->unique(['delivery_agent_id', 'delivery_location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_charges');
    }
};
