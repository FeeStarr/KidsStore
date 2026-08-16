<?php

namespace App\Observers;

use App\Models\CustomOrder;
use App\Models\Order;
use App\Services\CustomOrderService;

class OrderObserver
{
    public function __construct(
        private CustomOrderService $customOrderService
    ) {}

    public function updated(Order $order): void
    {
        if (!$order->custom_order_id) {
            return;
        }

        $customOrder = CustomOrder::find($order->custom_order_id);
        if (!$customOrder) {
            return;
        }

        // When payment is confirmed, update custom order to paid status
        if ($order->wasChanged('payment_status') && $order->payment_status === 'paid') {
            if (in_array($customOrder->status, [
                CustomOrder::STATUS_CUSTOMER_APPROVED,
                CustomOrder::STATUS_PAYMENT_PENDING,
                CustomOrder::STATUS_PAID,
            ])) {
                $this->customOrderService->transitionTo(
                    $customOrder,
                    CustomOrder::STATUS_PAID,
                    null
                );

                $customOrder->update([
                    'amount_paid' => $order->amount_paid,
                    'payment_status' => 'paid',
                ]);
            }
        }
    }
}
