<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\NotificationRecipients;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Foundation\Bus\Dispatchable;
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
            try {
                $order->customer->notify(new OrderPlacedNotification($order));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('OrderPlaced notification to customer failed', [
                    'error' => $e->getMessage(),
                    'order' => $order->reference,
                    'email' => $order->customer->email,
                ]);
            }
        } elseif ($order->guest_email) {
            try {
                \Illuminate\Support\Facades\Mail::to($order->guest_email)
                    ->send(new OrderPlacedNotification($order));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('OrderPlaced notification to guest failed', [
                    'error' => $e->getMessage(),
                    'order' => $order->reference,
                    'email' => $order->guest_email,
                ]);
            }
        }

        foreach (NotificationRecipients::adminUsers() as $admin) {
            try {
                $admin->notify(new OrderPlacedNotification($order));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('OrderPlaced notification to admin failed', [
                    'error' => $e->getMessage(),
                    'order' => $order->reference,
                    'email' => $admin->email,
                ]);
            }
        }

        foreach (NotificationRecipients::internalStaff() as $staff) {
            try {
                $staff->notify(new OrderPlacedNotification($order));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('OrderPlaced notification to staff failed', [
                    'error' => $e->getMessage(),
                    'order' => $order->reference,
                    'email' => $staff->email,
                ]);
            }
        }
    }
}
