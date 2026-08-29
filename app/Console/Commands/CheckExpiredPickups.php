<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckExpiredPickups extends Command
{
    protected $signature = 'pickups:check-expired {--days=4}';
    protected $description = 'Auto-expire orders past the 4-day pickup collection window';

    public function handle(OrderService $orders): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $this->info("Checking for orders past the {$days}-day pickup window...");

        $expiredOrders = Order::where('status', 'ready for pick up')
            ->whereHas('items', function ($q) {
                $q->where('pickup_status', 'ready for pickup');
            })
            ->whereNotNull('ready_for_pickup_at')
            ->where('ready_for_pickup_at', '<=', $cutoff)
            ->with(['items' => fn($q) => $q->where('pickup_status', 'ready for pickup'), 'pickupStation'])
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->info('No expired pickup orders found.');
            return Command::SUCCESS;
        }

        $this->warn("Found {$expiredOrders->count()} expired order(s). Marking as 'pickup window expired'...");

        foreach ($expiredOrders as $order) {
            $daysElapsed = $order->ready_for_pickup_at->diffInDays(now());

            $orders->markPickupWindowExpired($order);

            $this->line("  <info>{$order->reference}</info> - {$order->pickupStation?->name ?? 'N/A'} - {$daysElapsed} days elapsed");
        }

        $this->newLine();
        $this->info("Done. {$expiredOrders->count()} order(s) marked as 'pickup window expired'.");

        return Command::SUCCESS;
    }
}
