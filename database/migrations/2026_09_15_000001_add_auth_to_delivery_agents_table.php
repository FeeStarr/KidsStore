<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_agents', 'account_number')) {
                $table->string('account_number', 20)->unique()->after('id');
            }
            if (! Schema::hasColumn('delivery_agents', 'user_id')) {
                $table->foreignId('user_id')->nullable()->unique()->after('account_number')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_agents', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('delivery_agents', 'account_number')) {
                $table->dropColumn('account_number');
            }
        });
    }
};
