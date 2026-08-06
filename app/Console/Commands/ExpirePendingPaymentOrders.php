<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class ExpirePendingPaymentOrders extends Command
{
    protected $signature = 'payments:expire-pending';
    protected $description = 'Expire pending payment orders older than 24 hours';

    public function handle(): int
    {
        $cutoff = now()->subHours(24);

        $orders = Order::where('status', 'pending payment')
            ->where('created_at', '<=', $cutoff)
            ->get();

        $count = 0;

        foreach ($orders as $order) {
            $order->update(['status' => 'expired']);

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
