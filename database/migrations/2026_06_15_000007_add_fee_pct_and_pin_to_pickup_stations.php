<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_stations', function (Blueprint $table) {
            // Commission percentage the station earns on each order's grand_total
            $table->decimal('fee_pct', 5, 2)->default(0)->after('is_active')
                ->comment('% of order grand_total owed to this station per delivered order');
            // Hashed PIN for staff portal access
            $table->string('access_pin')->nullable()->after('fee_pct')
                ->comment('bcrypt-hashed PIN for staff portal login');
        });
    }

    public function down(): void
    {
        Schema::table('pickup_stations', function (Blueprint $table) {
            $table->dropColumn(['fee_pct', 'access_pin']);
        });
    }
};
