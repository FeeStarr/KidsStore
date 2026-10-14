<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One payable order per custom order.
     *
     * This is the last line of defence behind the row lock and the explicit guard in
     * CustomQuoteService::approveAndCreateOrder(): even if two approvals somehow ran
     * concurrently past those checks, the database itself refuses the second order.
     *
     * MySQL treats NULLs as distinct in a unique index, so every normal order (which
     * has no custom_order_id) is unaffected.
     */
    public function up(): void
    {
        $duplicates = DB::table('orders')
            ->select('custom_order_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('custom_order_id')
            ->groupBy('custom_order_id')
            ->having('total', '>', 1)
            ->pluck('custom_order_id');

        if ($duplicates->isNotEmpty()) {
            // Fail loudly rather than quietly deleting or merging orders - these are
            // financial records and the resolution is a human decision.
            throw new RuntimeException(
                'Cannot add unique index on orders.custom_order_id: duplicate linked orders exist for custom_order_id '
                .$duplicates->implode(', ')
                .'. Resolve these manually before migrating; do not delete or merge records automatically.'
            );
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('custom_order_id', 'orders_custom_order_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_custom_order_id_unique');
        });
    }
};
