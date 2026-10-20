<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which design a custom request was made from, and whether that design asked for
     * the child's measurements to be confirmed before production.
     *
     * The requirement is recorded on the order rather than looked up from the design
     * at gate time, because the obligation belongs to the request: a design's flag may
     * change later, but the promise made to the customer when they asked cannot.
     */
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('custom_creation_id')->nullable()->after('base_product_id');
            $table->boolean('requires_measurement_confirmation')->default(false)->after('custom_creation_id');

            $table->index('custom_creation_id');
            $table->foreign('custom_creation_id')
                ->references('id')
                ->on('custom_creations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropForeign(['custom_creation_id']);
            $table->dropIndex(['custom_creation_id']);
            $table->dropColumn(['custom_creation_id', 'requires_measurement_confirmation']);
        });
    }
};
