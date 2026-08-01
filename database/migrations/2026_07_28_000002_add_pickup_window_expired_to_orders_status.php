<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'ordered',
            'pending confirmation',
            'confirmed',
            'processing',
            'shipping to station',
            'out for delivery',
            'ready for pick up',
            'delivered',
            'cancelled',
            'pickup window expired'
        ) NOT NULL DEFAULT 'ordered'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'ordered',
            'pending confirmation',
            'confirmed',
            'processing',
            'shipping to station',
            'out for delivery',
            'ready for pick up',
            'delivered',
            'cancelled'
        ) NOT NULL DEFAULT 'ordered'");
    }
};
