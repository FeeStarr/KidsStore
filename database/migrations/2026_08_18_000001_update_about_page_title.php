<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('about_page')
            ->where('hero_title', 'About Kids Store')
            ->update(['hero_title' => 'About KidsFlairr']);
    }

    public function down(): void
    {
        DB::table('about_page')
            ->where('hero_title', 'About KidsFlairr')
            ->update(['hero_title' => 'About Kids Store']);
    }
};
