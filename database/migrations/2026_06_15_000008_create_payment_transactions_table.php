<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // OPay reference we generate (unique per attempt)
            $table->string('reference')->unique();

            // OPay's own order number returned on create
            $table->string('opay_order_no')->nullable();

            // Virtual account Opay gives the customer to transfer to
            $table->string('virtual_account_number')->nullable();
            $table->string('virtual_bank_name')->nullable();

            // Amount in Naira (NOT kobo - we convert internally)
            $table->decimal('amount', 12, 2);

            // pending | success | failed | expired | cancelled
            $table->string('status')->default('pending');

            // When the virtual account expires
            $table->timestamp('expires_at')->nullable();

            // Raw OPay callback payload (for audit)
            $table->json('opay_payload')->nullable();

            // Who queried/verified last
            $table->timestamp('last_queried_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
