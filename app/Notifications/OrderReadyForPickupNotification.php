<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the customer when their order items are ready for pickup at the station.
 *
 * TO:  Customer
 * BCC: Admin + superadmin
 */
class OrderReadyForPickupNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $station = $order->pickupStation;
        $stationName = $station?->name ?? 'our pickup station';
        $stationAddress = $station?->address ?? '';

        $message = (new MailMessage)
            ->subject("Your order is ready for pickup — {$order->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Great news! Your order **{$order->reference}** is now ready for pickup.")
            ->line("**Pickup Location:** {$stationName}")
            ->line('')
            ->action('View Order Details', url('/account/orders/' . $order->id))
            ->line("Please bring a valid ID and your order reference when collecting your items.");

        if ($stationAddress) {
            $message->line("**Address:** {$stationAddress}");
        }

        if ($station?->instructions) {
            $message->line("**Station Instructions:** {$station->instructions}");
        }

        $message->line('')
            ->line("If you have questions, please contact our support team.");

        // BCC admins
        foreach (NotificationRecipients::adminUsers() as $admin) {
            if ($admin->id !== $notifiable->id) {
                $message->bcc($admin->email, $admin->name);
            }
        }

        return $message;
    }
}
