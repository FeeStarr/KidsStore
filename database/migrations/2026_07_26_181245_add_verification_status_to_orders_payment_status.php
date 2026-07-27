<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status ENUM('unpaid','partial','paid','refunded','verification_pending','verification_failed') DEFAULT 'unpaid'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status ENUM('unpaid','partial','paid','refunded') DEFAULT 'unpaid'");
    }
};
