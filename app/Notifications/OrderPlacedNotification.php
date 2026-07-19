<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a new order is placed.
 *
 * TO:  Customer
 * CC:  Customer support staff
 * BCC: Admin + superadmin
 *
 * Also sent separately to internal staff (TO: staff, BCC: admin/superadmin).
 */
class OrderPlacedNotification extends Notification
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
        $isInternal = $notifiable->isAdmin() || $notifiable->isStaff();

        $subject = $isInternal
            ? "New Order Placed — {$order->reference}"
            : "Your order has been received — {$order->reference}";

        $message = (new MailMessage)
            ->subject($subject);

        if ($isInternal) {
            $message->greeting("New order from {$order->customer?->name}")
                    ->line("A new order **{$order->reference}** has been placed.")
                    ->line("**Customer:** {$order->customer?->name} ({$order->customer?->email})")
                    ->line("**Delivery:** {$order->getDeliveryMethodLabel()}")
                    ->line("**Total:** ₦" . number_format($order->grand_total, 2))
                    ->action('View Order', url('/admin/orders/' . $order->id));

            foreach (NotificationRecipients::adminUsers() as $admin) {
                if ($admin->id !== $notifiable->id) {
                    $message->cc($admin->email, $admin->name);
                }
            }
        } else {
            $order->loadMissing('items.product', 'items.variant.image', 'items.variant.images', 'pickupStation');
            $message->view('emails.order-placed', ['order' => $order]);
        }

        return $message;
    }
}
