<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPickupFees extends Command
{
    protected $signature = 'backfill:pickup-fees {--batch=500} {--dry-run}';
    protected $description = 'Backfill orders.pickup_station_fee_total from order_items.pickup_station_fee (idempotent)';

    public function handle()
    {
        $batch = (int) $this->option('batch');
        $dry = (bool) $this->option('dry-run');

        $this->info('Starting pickup fee backfill'.($dry ? ' (dry run)' : '')." - batch={$batch}");

        $totalOrders = DB::table('orders')->count();
        $this->info("Orders to check: {$totalOrders}");

        $processed = 0;

        DB::table('orders')->orderBy('id')->chunk($batch, function($orders) use (&$processed, $dry) {
            foreach ($orders as $o) {
                $sum = (float) DB::table('order_items')->where('order_id', $o->id)->sum('pickup_station_fee');
                if ((float) $o->pickup_station_fee_total !== $sum) {
                    $this->line("Order {$o->id}: updating {$o->pickup_station_fee_total} → {$sum}");
                    if (! $dry) {
                        DB::table('orders')->where('id', $o->id)->update(['pickup_station_fee_total' => $sum]);
                    }
                }
                $processed++;
            }
        });

        $this->info("Processed {$processed} orders. Done.");

        return 0;
    }
}
