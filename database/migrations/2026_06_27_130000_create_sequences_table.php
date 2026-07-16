<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('name', 100)->primary();
            $table->unsignedBigInteger('value')->default(1);
            $table->timestamps();
        });

        // Initialize product_code sequence to max(products.id) + 1 to avoid collisions
        $start = 1;
        try {
            if (Schema::hasTable('products')) {
                $max = DB::table('products')->max('id');
                $start = ($max !== null) ? ((int) $max + 1) : 1;
            }
        } catch (\Throwable $e) {
            // ignore; default start=1
        }

        DB::table('sequences')->insert([
            'name' => 'product_code',
            'value' => $start,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
