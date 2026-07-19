<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_stations', function (Blueprint $table) {
            if (! Schema::hasColumn('pickup_stations', 'is_available')) {
                $table->boolean('is_available')->default(true)->after('is_active');
            }
            if (! Schema::hasColumn('pickup_stations', 'unavailability_reason')) {
                $table->text('unavailability_reason')->nullable()->after('is_available');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pickup_stations', function (Blueprint $table) {
            $table->dropColumn(['is_available', 'unavailability_reason']);
        });
    }
};
