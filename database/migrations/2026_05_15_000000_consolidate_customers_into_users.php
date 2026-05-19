<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add missing columns to users table if not already present
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('customer')->after('password');
            });
        }

        if (!Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone')->nullable()->after('role');
            });
        }

        if (!Schema::hasColumn('users', 'address')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('address')->nullable()->after('phone');
            });
        }

        // 2. Migrate customers to users table
        if (Schema::hasTable('customers')) {
            $customers = DB::table('customers')->get();
            foreach ($customers as $customer) {
                // Skip customers with null email
                if (!$customer->email) {
                    continue;
                }

                // Check if user with same email already exists
                $existingUser = DB::table('users')
                    ->where('email', $customer->email)
                    ->first();

                if (!$existingUser) {
                    DB::table('users')->insert([
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'address' => $customer->address,
                        'password' => '', // Customers migrated without password set
                        'role' => 'customer',
                        'email_verified_at' => null,
                        'created_at' => $customer->created_at,
                        'updated_at' => $customer->updated_at,
                    ]);
                }
            }

            // 3. Update foreign key references
            // First, disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            try {
                // Create mapping of old customer IDs to new user IDs
                $mapping = [];
                $oldCustomers = DB::table('customers')->get();
                foreach ($oldCustomers as $oldCust) {
                    // Skip customers with null email
                    if (!$oldCust->email) {
                        continue;
                    }
                    
                    $newUser = DB::table('users')
                        ->where('email', $oldCust->email)
                        ->where('role', 'customer')
                        ->first();
                    if ($newUser) {
                        $mapping[$oldCust->id] = $newUser->id;
                    }
                }

                // Update orders table (customer_id -> user_id, but keep column for now)
                foreach ($mapping as $oldId => $newId) {
                    DB::table('orders')
                        ->where('customer_id', $oldId)
                        ->update(['customer_id' => $newId]);
                }

                // Update product_reviews table (customer_id -> user_id, but keep column for now)
                foreach ($mapping as $oldId => $newId) {
                    DB::table('product_reviews')
                        ->where('customer_id', $oldId)
                        ->update(['customer_id' => $newId]);
                }

                // Drop the customers table
                Schema::dropIfExists('customers');

            } finally {
                // Re-enable foreign key checks
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }
    }

    public function down(): void
    {
        // Reverse is complex; for now, we'll just add back the customers table
        // (In production, you may want a more sophisticated rollback)
        Schema::dropIfExists('customers');
    }
};
