<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\NotificationRecipients;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendOrderPlacedNotifications
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly int $orderId)
    {
    }

    public function handle(): void
    {
        $order = Order::with('customer', 'items.product', 'items.variant.image', 'items.variant.images', 'pickupStation')->find($this->orderId);

        if (! $order) {
            return;
        }

        if ($order->customer) {
            $order->customer->notify(new OrderPlacedNotification($order));
        }

        foreach (NotificationRecipients::adminUsers() as $admin) {
            $admin->notify(new OrderPlacedNotification($order));
        }

        foreach (NotificationRecipients::internalStaff() as $staff) {
            $staff->notify(new OrderPlacedNotification($order));
        }
    }
}
