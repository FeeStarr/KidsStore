<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'cod' to payments.method enum
        DB::statement("ALTER TABLE payments MODIFY method ENUM('cash','card','transfer','mobile','other','cod') NOT NULL DEFAULT 'cash'");
    }

    public function down(): void
    {
        // Revert to previous enum (remove 'cod')
        DB::statement("ALTER TABLE payments MODIFY method ENUM('cash','card','transfer','mobile','other') NOT NULL DEFAULT 'cash'");
    }
};
