<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('guest_name')->nullable()->after('customer_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone')->nullable()->after('guest_email');
            $table->string('lookup_token', 64)->nullable()->unique()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_email', 'guest_phone', 'lookup_token']);
        });
    }
};
