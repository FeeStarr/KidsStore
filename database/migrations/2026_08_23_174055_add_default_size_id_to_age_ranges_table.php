<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('age_ranges', function (Blueprint $table) {
            $table->foreignId('default_size_id')->nullable()->after('is_active')->constrained('sizes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('age_ranges', function (Blueprint $table) {
            $table->dropForeign(['default_size_id']);
            $table->dropColumn('default_size_id');
        });
    }
};
