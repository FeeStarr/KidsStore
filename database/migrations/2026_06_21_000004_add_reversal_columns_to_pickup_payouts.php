<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_payouts', function (Blueprint $table) {
            $table->boolean('is_reversed')->default(false)->after('note');
            $table->foreignId('reversed_by')->nullable()->after('is_reversed')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('reversed_by');
        });
    }

    public function down(): void
    {
        Schema::table('pickup_payouts', function (Blueprint $table) {
            $table->dropColumn(['reversed_at']);
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['is_reversed']);
        });
    }
};
