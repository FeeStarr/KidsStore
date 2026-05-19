<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot existing values keyed by id, then convert column to JSON.
        $existing = DB::table('products')->pluck('age_group', 'id');

        Schema::table('products', function (Blueprint $table) {
            $table->json('age_group_tmp')->nullable()->after('age_group');
        });

        foreach ($existing as $id => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $arr = array_values(array_filter(array_map('trim', explode(',', $value))));
            DB::table('products')->where('id', $id)->update([
                'age_group_tmp' => json_encode($arr),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('age_group');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('age_group_tmp', 'age_group');
        });
    }

    public function down(): void
    {
        $existing = DB::table('products')->pluck('age_group', 'id');

        Schema::table('products', function (Blueprint $table) {
            $table->string('age_group_tmp', 64)->nullable()->after('age_group');
        });

        foreach ($existing as $id => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $arr = json_decode($value, true) ?: [];
            DB::table('products')->where('id', $id)->update([
                'age_group_tmp' => is_array($arr) ? implode(',', $arr) : (string) $value,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('age_group');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('age_group_tmp', 'age_group');
        });
    }
};
