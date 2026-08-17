<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('social_whatsapp')->nullable()->after('address');
            $table->string('social_instagram')->nullable()->after('social_whatsapp');
            $table->string('social_facebook')->nullable()->after('social_instagram');
            $table->string('social_tiktok')->nullable()->after('social_facebook');
            $table->string('social_twitter')->nullable()->after('social_tiktok');
            $table->string('social_website')->nullable()->after('social_twitter');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'social_whatsapp', 'social_instagram', 'social_facebook',
                'social_tiktok', 'social_twitter', 'social_website',
            ]);
        });
    }
};
