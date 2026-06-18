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
            ->subject($subject)
            ->greeting($isInternal ? "New order from {$order->customer?->name}" : "Hello {$notifiable->name},");

        if ($isInternal) {
            $message->line("A new order **{$order->reference}** has been placed.")
                    ->line("**Customer:** {$order->customer?->name} ({$order->customer?->email})")
                    ->line("**Delivery:** {$order->getDeliveryMethodLabel()}")
                    ->line("**Total:** ₦" . number_format($order->grand_total, 2))
                    ->action('View Order', url('/admin/orders/' . $order->id));
        } else {
            $message->line("Thank you for your order! We've received it and will begin processing shortly.")
                    ->line("**Order Reference:** {$order->reference}")
                    ->line("**Order Date:** " . $order->order_date->format('M d, Y'))
                    ->line("**Delivery Method:** {$order->getDeliveryMethodLabel()}")
                    ->line("**Total:** ₦" . number_format($order->grand_total, 2))
                    ->line("**Estimated Delivery:** " . $order->delivery_window)
                    ->action('View Your Order', url('/account/orders/' . $order->id))
                    ->line("If you have any questions, please contact our support team.");
        }

        // If this is an internal staff email, CC admins/superadmins
        if ($isInternal) {
            foreach (NotificationRecipients::adminUsers() as $admin) {
                if ($admin->id !== $notifiable->id) {
                    $message->cc($admin->email, $admin->name);
                }
            }
        }

        return $message;
    }
}
