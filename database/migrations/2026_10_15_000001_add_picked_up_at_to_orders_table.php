<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fulfilment timestamps for orders.
     *
     * Two gaps made a "7 days from delivery" reporting window impossible to honour:
     *
     *  1. Pickup orders had no collection timestamp at all. PickupStationService sets
     *     status to 'delivered' when the last item is collected, but wrote no timestamp.
     *  2. Order::markAsDelivered() set status 'delivered' without delivered_at.
     *
     * picked_up_at records actual collection. delivered_at stays reserved for courier
     * delivery fulfilment and is deliberately NOT set for pickup orders.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'picked_up_at')) {
            Schema::table('orders', function ($table) {
                $table->dateTime('picked_up_at')->nullable()->after('delivered_at');
            });
        }

        // Conservative backfill: only courier-delivery orders already marked delivered
        // that are missing delivered_at, and only where delivery_delivered_at already
        // holds a real recorded value.
        //
        // delivery_method is the sole classifier - it is a NOT NULL enum defaulting to
        // 'delivery'. pickup_station_id is deliberately NOT used: it is nullable and
        // some checkout paths assign it without checking delivery_method, so a
        // "pickup_station_id IS NULL" predicate would misclassify delivery orders.
        $patched = DB::table('orders')
            ->where('delivery_method', 'delivery')
            ->where('status', 'delivered')
            ->whereNull('delivered_at')
            ->whereNotNull('delivery_delivered_at')
            ->update(['delivered_at' => DB::raw('delivery_delivered_at')]);

        // Historical pickup orders are intentionally left with no collection time.
        // updated_at is never used to infer a date - it shifts on any edit and would
        // silently start the reporting clock from a meaningless moment. Those cases
        // surface as unknown and require manual verification.
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'picked_up_at')) {
            Schema::table('orders', function ($table) {
                $table->dropColumn('picked_up_at');
            });
        }
    }
};
