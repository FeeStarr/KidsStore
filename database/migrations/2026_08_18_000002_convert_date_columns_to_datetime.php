<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE orders MODIFY order_date DATETIME NOT NULL');
        DB::statement('ALTER TABLE orders MODIFY expected_delivery_date DATETIME NULL');
        DB::statement('ALTER TABLE purchases MODIFY purchase_date DATETIME NOT NULL');
        DB::statement('ALTER TABLE payments MODIFY payment_date DATETIME NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders MODIFY order_date DATE NOT NULL');
        DB::statement('ALTER TABLE orders MODIFY expected_delivery_date DATE NULL');
        DB::statement('ALTER TABLE purchases MODIFY purchase_date DATE NOT NULL');
        DB::statement('ALTER TABLE payments MODIFY payment_date DATE NOT NULL');
    }
};
