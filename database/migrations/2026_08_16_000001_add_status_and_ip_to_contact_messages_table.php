<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('status', 16)->default('new')->after('message');
            $table->string('ip_address', 45)->nullable()->after('status');
        });

        // Migrate existing read/unread data to new status field
        DB::table('contact_messages')->where('read', true)->update(['status' => 'read']);
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn(['status', 'ip_address']);
        });
    }
};
