<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_order_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_order_id')->constrained('custom_orders')->cascadeDelete();
            $table->string('file_type', 32); // reference_image, colour_reference, production_photo, qc_photo
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['custom_order_id', 'file_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_order_files');
    }
};
