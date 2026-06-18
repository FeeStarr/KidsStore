<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'selected_age_group')) {
                $table->string('selected_age_group', 32)->nullable()->after('product_variant_id');
            }
            if (! Schema::hasColumn('order_items', 'selected_size')) {
                $table->string('selected_size', 64)->nullable()->after('selected_age_group');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'selected_size')) {
                $table->dropColumn('selected_size');
            }
            if (Schema::hasColumn('order_items', 'selected_age_group')) {
                $table->dropColumn('selected_age_group');
            }
        });
    }
};
