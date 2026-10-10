<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Product edits previously wrote only inventories.quantity via a raw
     * relation update, bypassing Inventory's dual-write mutators and leaving
     * quantity_on_hand (shown on the admin inventory page) stale. Sync the
     * legacy column from the authoritative one and rebuild product caches.
     */
    public function up(): void
    {
        DB::table('inventories')
            ->whereColumn('quantity', '!=', 'quantity_on_hand')
            ->update(['quantity' => DB::raw('quantity_on_hand')]);

        DB::statement("
            UPDATE products p
            SET stock_quantity = (
                SELECT COALESCE(SUM(i.quantity), 0)
                FROM inventories i
                JOIN product_variants pv ON pv.id = i.product_variant_id
                WHERE pv.product_id = p.id
            )
        ");
    }

    public function down(): void
    {
        // Data sync is not reversible.
    }
};
