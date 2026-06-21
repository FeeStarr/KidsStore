<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use raw SQL to avoid requiring doctrine/dbal for simple nullability change
        DB::statement('ALTER TABLE `pickup_payout_items` MODIFY `order_item_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Revert to NOT NULL (use cautious default 0 if any nulls exist)
        DB::statement('UPDATE `pickup_payout_items` SET `order_item_id` = 0 WHERE `order_item_id` IS NULL');
        DB::statement('ALTER TABLE `pickup_payout_items` MODIFY `order_item_id` BIGINT UNSIGNED NOT NULL');
    }
};
