<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:expire-pending')->hourly();
Schedule::command('pickups:check-expired')->daily();
Schedule::command('check-custom-quote-expiry')->daily();
Schedule::command('sync-deal-statuses')->hourly();

// Backfill pickup fees from order_items into orders.pickup_station_fee_total
Artisan::command('backfill:pickup-fees {--batch=500} {--dry-run}', function () {
    $batch = (int) $this->option('batch');
    $dry = (bool) $this->option('dry-run');

    $this->info('Starting pickup fee backfill'.($dry ? ' (dry run)' : '')." - batch={$batch}");

    $totalOrders = DB::table('orders')->count();
    $this->info("Orders to check: {$totalOrders}");

    $processed = 0;

    DB::table('orders')->orderBy('id')->chunk($batch, function ($orders) use (&$processed, $dry) {
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
})->describe('Backfill orders.pickup_station_fee_total from order_items');
