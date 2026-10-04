<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_order_messages', function (Blueprint $table) {
            $table->foreignId('custom_order_file_id')
                ->nullable()
                ->after('message')
                ->constrained('custom_order_files')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_order_messages', function (Blueprint $table) {
            $table->dropForeign(['custom_order_file_id']);
            $table->dropColumn('custom_order_file_id');
        });
    }
};
