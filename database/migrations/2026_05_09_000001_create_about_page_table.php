<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('about_page', function (Blueprint $table) {
            $table->id();
            $table->string('hero_title')->default('About Kids Store');
            $table->string('hero_subtitle')->default('Where little dreams come to play!');
            $table->text('story')->nullable();
            $table->text('mission')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        // Seed with default content
        DB::table('about_page')->insert([
            'hero_title'    => 'About Kids Store',
            'hero_subtitle' => 'Where little dreams come to play!',
            'story'         => 'Kids Store was founded by parents who wanted to make it easy to find safe, fun, and affordable products for children of all ages. We carefully handpick every item in our collection to ensure it meets the highest quality and safety standards.',
            'mission'       => 'Our mission is to bring joy to every child and peace of mind to every parent. We believe every kid deserves the best — from the toys they play with to the clothes they wear.',
            'email'         => 'hello@kidsstore.example',
            'phone'         => '+234 800 000 0000',
            'address'       => '12 Happy Lane, Lagos, Nigeria',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('about_page');
    }
};
