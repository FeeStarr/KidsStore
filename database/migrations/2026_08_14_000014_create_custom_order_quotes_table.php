<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_order_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_order_id')->constrained('custom_orders')->cascadeDelete();
            $table->unsignedInteger('version')->default(1);

            $table->decimal('base_price', 14, 2)->default(0);
            $table->decimal('fabric_cost', 12, 2)->default(0);
            $table->decimal('customization_cost', 12, 2)->default(0);
            $table->decimal('embellishment_cost', 12, 2)->default(0);
            $table->decimal('measurement_fee', 12, 2)->default(0);
            $table->decimal('rush_fee', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->json('breakdown')->nullable(); // line-item array for display
            $table->timestamp('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('draft'); // draft, superseded, approved, rejected
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('reminder_sent')->default(false);

            $table->index(['custom_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_order_quotes');
    }
};
