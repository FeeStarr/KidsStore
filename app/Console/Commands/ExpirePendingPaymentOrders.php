<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Contracts\InventoryServiceInterface;
use Illuminate\Console\Command;

class ExpirePendingPaymentOrders extends Command
{
    protected $signature = 'payments:expire-pending';
    protected $description = 'Expire pending payment orders older than 24 hours and release their stock reservations';

    public function __construct(private InventoryServiceInterface $inventory)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $cutoff = now()->subHours(24);

        $orders = Order::where('status', 'pending payment')
            ->where('created_at', '<=', $cutoff)
            ->get();

        $count = 0;

        foreach ($orders as $order) {
            $order->update(['status' => 'expired']);

            // Release any stock reserved for this unpaid order
            $this->inventory->reverseMovementsFor(Order::class, $order->id, 'Order expired — unpaid');

            // Also expire any pending payment transactions
            $order->paymentTransactions()
                ->where('status', 'pending')
                ->update(['status' => 'expired']);

            $count++;
        }

        if ($count > 0) {
            $this->info("Expired {$count} pending payment order(s).");
        } else {
            $this->info('No pending payment orders to expire.');
        }

        return self::SUCCESS;
    }
}
