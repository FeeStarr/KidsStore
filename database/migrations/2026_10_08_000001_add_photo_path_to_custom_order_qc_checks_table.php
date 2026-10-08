<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_order_qc_checks', function (Blueprint $table) {
            $table->string('photo_path', 500)->nullable()->after('checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('custom_order_qc_checks', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
