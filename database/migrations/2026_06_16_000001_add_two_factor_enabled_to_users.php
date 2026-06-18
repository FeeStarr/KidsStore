<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_expires_at');
        });

        // Pre-enable 2FA for all existing admin/superadmin/staff users
        \Illuminate\Support\Facades\DB::statement(
            "UPDATE users SET two_factor_enabled = 1 WHERE role IN ('admin', 'superadmin', 'staff')"
        );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_enabled');
        });
    }
};
