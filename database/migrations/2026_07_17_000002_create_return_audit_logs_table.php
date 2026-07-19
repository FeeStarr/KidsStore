<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_request_id')->constrained()->cascadeOnDelete();
            $table->string('action'); // e.g. requested, approved, rejected, evidence_uploaded, refund_processed
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // admin who performed action
            $table->text('details')->nullable();
            $table->json('metadata')->nullable(); // extra context (old_status, new_status, etc.)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_audit_logs');
    }
};
