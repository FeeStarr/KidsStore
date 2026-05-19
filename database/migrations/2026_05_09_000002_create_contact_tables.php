<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_page', function (Blueprint $table) {
            $table->id();
            $table->string('hero_title')->default('Contact Us');
            $table->string('hero_subtitle')->default('We\'d love to hear from you!');
            $table->text('intro')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('hours')->nullable();
            $table->timestamps();
        });

        DB::table('contact_page')->insert([
            'hero_title'    => 'Contact Us',
            'hero_subtitle' => 'We\'d love to hear from you!',
            'intro'         => 'Have a question, a suggestion, or just want to say hello? Fill in the form below and our friendly team will get back to you as soon as possible.',
            'email'         => 'hello@kidsstore.example',
            'phone'         => '+234 800 000 0000',
            'address'       => '12 Happy Lane, Lagos, Nigeria',
            'hours'         => 'Mon – Fri: 9 am – 6 pm',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('contact_page');
    }
};
