<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\NotificationRecipients;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Bus\Dispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderPlacedNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(public readonly int $orderId)
    {
    }

    public function handle(): void
    {
        $order = Order::with('customer', 'items.product', 'items.variant', 'pickupStation')->find($this->orderId);

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
