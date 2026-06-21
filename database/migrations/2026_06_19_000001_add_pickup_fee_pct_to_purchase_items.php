<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_items', 'pickup_fee_pct')) {
                $table->decimal('pickup_fee_pct', 5, 2)->default(0)->after('line_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_items', 'pickup_fee_pct')) {
                $table->dropColumn('pickup_fee_pct');
            }
        });
    }
};
