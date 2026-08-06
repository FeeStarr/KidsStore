<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the customer when their order item has been received at the pickup station.
 *
 * TO:  Customer
 * BCC: Admin
 */
class OrderItemReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Order     $order,
        public readonly OrderItem $item,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $item  = $this->item;

        $productName = $item->product?->name ?? 'your item';
        $stationName = $order->pickupStation?->name ?? 'the pickup station';

        $message = (new MailMessage)
            ->subject("Your item has arrived at {$stationName} — {$order->reference}")
            ->replyTo(config('emails.support'), 'KidsFlairr Support')
            ->greeting("Hello {$notifiable->name},")
            ->line("Good news! **{$productName}** has arrived at **{$stationName}** and has been marked as received.")
            ->line('')
            ->line("**Order Reference:** {$order->reference}")
            ->line("**Station:** {$stationName}");

        if ($order->pickupStation?->address) {
            $message->line("**Station Address:** {$order->pickupStation->address}");
        }

        $message->line('')
            ->line("You'll receive another email once your item is ready for pickup.")
            ->action('View Your Order', url('/account/orders/' . $order->id))
            ->line('If you have questions, please contact our support team.');

        // BCC admins
        foreach (NotificationRecipients::adminUsers() as $admin) {
            if ($admin->id !== $notifiable->id) {
                $message->bcc($admin->email, $admin->name);
            }
        }

        // BCC orders mailbox for record-keeping
        $message->bcc(config('emails.orders'));

        return $message;
    }
}
