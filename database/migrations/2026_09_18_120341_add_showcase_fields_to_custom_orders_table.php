<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->boolean('showcase_enabled')->default(false)->after('return_policy_acknowledged');
            $table->string('showcase_title')->nullable()->after('showcase_enabled');
            $table->decimal('showcase_price', 12, 2)->nullable()->after('showcase_title');
            $table->text('showcase_description')->nullable()->after('showcase_price');
            $table->string('showcase_image_path')->nullable()->after('showcase_description');
            $table->string('showcase_category')->nullable()->after('showcase_image_path');

            $table->index('showcase_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropIndex(['showcase_enabled']);
            $table->dropColumn([
                'showcase_enabled',
                'showcase_title',
                'showcase_price',
                'showcase_description',
                'showcase_image_path',
                'showcase_category',
            ]);
        });
    }
};
