<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The consolidate_customers_into_users migration dropped the customers table
 * but left the FK on orders.customer_id still referencing it.
 * Re-point it to the users table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Disable FK checks so we can swap the constraint safely.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // Drop any existing constraint on orders.customer_id
            DB::statement('ALTER TABLE orders DROP FOREIGN KEY IF EXISTS orders_customer_id_foreign');
        } catch (\Throwable) {
            // Constraint name may differ — try the generic approach
        }

        // Re-add pointing to users
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_customer_id_foreign
            FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL');

        // Same fix for product_reviews if needed
        try {
            DB::statement('ALTER TABLE product_reviews DROP FOREIGN KEY IF EXISTS product_reviews_customer_id_foreign');
        } catch (\Throwable) {}

        DB::statement('ALTER TABLE product_reviews ADD CONSTRAINT product_reviews_customer_id_foreign
            FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::statement('ALTER TABLE orders DROP FOREIGN KEY IF EXISTS orders_customer_id_foreign');
        } catch (\Throwable) {}

        try {
            DB::statement('ALTER TABLE product_reviews DROP FOREIGN KEY IF EXISTS product_reviews_customer_id_foreign');
        } catch (\Throwable) {}

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
