<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_orders', function (Blueprint $table) {
            $table->id();
            $table->string('custom_order_number', 32)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeDelete();
            $table->string('item_type', 32)->default('frock');

            $table->string('status', 32)->default('draft');
            $table->string('payment_status', 32)->default('unpaid');

            $table->foreignId('base_product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('child_name')->nullable();
            $table->unsignedSmallInteger('child_age')->nullable();
            $table->string('child_gender', 16)->nullable();

            $table->string('delivery_method', 16)->default('delivery');
            $table->foreignId('pickup_station_id')->nullable()->constrained('pickup_stations')->nullOnDelete();
            $table->text('delivery_address')->nullable();

            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('production_notes')->nullable();
            $table->text('customer_visible_notes')->nullable();
            $table->text('custom_colour_description')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('customization_fee', 12, 2)->default(0);
            $table->decimal('rush_fee', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);

            $table->timestamp('quote_valid_until')->nullable();
            $table->unsignedSmallInteger('estimated_production_days')->nullable();

            $table->boolean('return_policy_acknowledged')->default(false);

            $table->string('tracking_number')->nullable();
            $table->string('courier_name')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('production_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_orders');
    }
};
