<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE order_items MODIFY COLUMN pickup_status ENUM('pending', 'received', 'ready for pickup', 'picked_up', 'expired') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE order_items MODIFY COLUMN pickup_status ENUM('pending', 'received', 'ready for pickup', 'picked_up') DEFAULT 'pending'");
    }
};
