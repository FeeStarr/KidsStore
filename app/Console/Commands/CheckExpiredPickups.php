<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\PickupStationService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckExpiredPickups extends Command
{
    protected $signature = 'pickups:check-expired {--days=7}';
    protected $description = 'Check for orders that have exceeded the pickup collection window';

    public function handle(PickupStationService $pickupService): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $this->info("Checking for orders older than {$days} days...");

        // Find orders where all items are ready but not picked up
        $expiredOrders = Order::where('status', 'ready for pick up')
            ->whereHas('items', function ($q) {
                $q->where('pickup_status', 'ready for pickup');
            })
            ->where('order_date', '<=', $cutoff)
            ->with(['items' => fn($q) => $q->where('pickup_status', 'ready for pickup'), 'pickupStation'])
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->info('No expired pickup orders found.');
            return Command::SUCCESS;
        }

        $this->warn("Found {$expiredOrders->count()} expired order(s):");

        $table = [];
        foreach ($expiredOrders as $order) {
            $daysExpired = $order->order_date->diffInDays(now());
            $table[] = [
                $order->reference,
                $order->pickupStation?->name ?? 'N/A',
                $order->order_date->format('Y-m-d'),
                $daysExpired . ' days',
                '₦' . number_format($order->grand_total, 2),
            ];
        }

        $this->table(['Reference', 'Station', 'Order Date', 'Days Expired', 'Amount'], $table);

        // List items that could have commission forfeited
        $this->newLine();
        $this->info('These orders have items marked "ready" that were never picked up.');
        $this->info('Commission for these items will NOT be paid unless the status is changed.');
        $this->info('');
        $this->info('Options:');
        $this->info('  1. Contact customer to collect');
        $this->info('  2. Mark items as abandoned (change status to "cancelled")');
        $this->info('  3. Admin can override status if needed');

        return Command::SUCCESS;
    }
}
