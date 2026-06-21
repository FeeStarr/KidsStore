<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_station_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('pickup_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_payout_id')->constrained('pickup_payouts')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->decimal('fee_amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_payout_items');
        Schema::dropIfExists('pickup_payouts');
    }
};
