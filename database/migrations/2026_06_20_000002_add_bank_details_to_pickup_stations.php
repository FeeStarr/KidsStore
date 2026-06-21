<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_stations', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('phone');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
            $table->text('bank_instructions')->nullable()->after('instructions');
        });
    }

    public function down(): void
    {
        Schema::table('pickup_stations', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_name', 'bank_account_number', 'bank_instructions']);
        });
    }
};
