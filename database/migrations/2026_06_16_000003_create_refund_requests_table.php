<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // null = full order refund; set = specific item refund
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();

            // How many units to refund (for partial item refunds)
            $table->unsignedInteger('quantity')->default(1);

            // Calculated refund amount
            $table->decimal('amount', 12, 2)->default(0);

            // pending | approved | rejected | refunded | failed
            $table->string('status')->default('pending');

            // Reason categories
            $table->string('reason');   // wrong_item | damaged | not_received | changed_mind | other
            $table->text('details')->nullable();

            // Evidence photo path
            $table->string('evidence_path')->nullable();

            // Admin response
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // OPay refund tracking
            $table->string('opay_refund_no')->nullable();
            $table->json('opay_payload')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
};
