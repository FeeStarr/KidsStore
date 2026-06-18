<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_size_id')->nullable()->after('product_variant_id');
            $table->foreign('variant_size_id')->references('id')->on('variant_sizes')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropForeign(['variant_size_id']);
            $table->dropColumn('variant_size_id');
        });
    }
};
