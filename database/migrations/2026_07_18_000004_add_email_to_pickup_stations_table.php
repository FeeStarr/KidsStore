<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_stations', function (Blueprint $table) {
            if (! Schema::hasColumn('pickup_stations', 'email')) {
                $table->string('email', 255)->after('phone');
            }
        });

        DB::table('pickup_stations')
            ->whereNull('email')
            ->orWhere('email', '')
            ->update(['email' => 'admin@kidsstore.com']);
    }

    public function down(): void
    {
        Schema::table('pickup_stations', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
