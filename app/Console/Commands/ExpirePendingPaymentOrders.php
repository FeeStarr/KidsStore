<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Contracts\InventoryServiceInterface;
use App\Services\CouponService;
use App\Services\DealService;
use Illuminate\Console\Command;

class ExpirePendingPaymentOrders extends Command
{
    protected $signature = 'payments:expire-pending';
    protected $description = 'Expire pending payment orders older than 24 hours and release their stock reservations';

    public function __construct(
        private InventoryServiceInterface $inventory,
        private DealService $deals,
        private CouponService $coupons,
    ) {
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
            $this->inventory->reverseMovementsFor(Order::class, $order->id, 'Order expired - unpaid');

            // Release deal usage reserved for this unpaid order
            foreach ($order->items()->whereNotNull('deal_id')->pluck('deal_id')->unique() as $dealId) {
                $this->deals->releaseUsage((int) $dealId);
            }

            // Release coupon usage reserved for this unpaid order
            $this->coupons->releaseForOrder($order);

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
