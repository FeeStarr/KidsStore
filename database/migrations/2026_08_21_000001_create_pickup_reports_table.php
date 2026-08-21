<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['missing_order', 'missing_item', 'damaged_item', 'wrong_item', 'customer_no_show', 'other']);
            $table->text('description');
            $table->enum('status', ['open', 'investigating', 'resolved', 'dismissed'])->default('open');
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['pickup_station_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_reports');
    }
};
