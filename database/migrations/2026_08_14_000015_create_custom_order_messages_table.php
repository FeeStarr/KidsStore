<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_order_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_order_id')->constrained('custom_orders')->cascadeDelete();
            $table->string('sender_type', 16); // customer, admin, staff
            $table->foreignId('sender_id')->constrained('users')->cascadeDelete();
            $table->text('message');
            $table->boolean('is_customer_visible')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('read_at')->nullable();

            $table->index(['custom_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_order_messages');
    }
};
