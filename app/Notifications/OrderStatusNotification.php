<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an order status changes (confirmed, processing, out for delivery,
 * ready for pick up, delivered, cancelled).
 *
 * TO:  Customer
 * BCC: Admin + superadmin
 */
class OrderStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Order  $order,
        public readonly string $previousStatus,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order  = $this->order;
        $status = $order->getStatusLabel();

        $subject = match ($order->status) {
            'confirmed'              => "Order {$order->reference} confirmed",
            'processing'             => "Order {$order->reference} is being processed",
            'shipping to station'    => "Order {$order->reference} is on its way to pickup station",
            'out for delivery'       => "Order {$order->reference} is on its way",
            'ready for pick up'      => "Order {$order->reference} is ready for pick up",
            'delivered'              => "Order {$order->reference} has been delivered",
            'cancelled'              => "Order {$order->reference} has been cancelled",
            'pickup window expired'  => "Order {$order->reference} - pickup window expired",
            default                  => "Update on your order {$order->reference}",
        };

        // For delivered status, use the rich HTML template with item thumbnails
        if ($order->status === 'delivered') {
            $order->loadMissing('items.product', 'items.variant.image', 'items.variant.images', 'pickupStation');

            $message = (new MailMessage)
                ->subject($subject)
                ->replyTo(config('emails.support'), 'KidsFlairr Support')
                ->view('emails.order-delivered', ['order' => $order]);

            // CC admins + staff
            foreach (NotificationRecipients::adminUsers() as $admin) {
                if ($admin->id !== $notifiable->id) {
                    $message->bcc($admin->email, $admin->name);
                }
            }
            foreach (NotificationRecipients::orderProcessingStaff() as $staff) {
                if ($staff->id !== $notifiable->id) {
                    $message->bcc($staff->email, $staff->name);
                }
            }
            foreach (NotificationRecipients::customerSupportStaff() as $staff) {
                if ($staff->id !== $notifiable->id) {
                    $message->bcc($staff->email, $staff->name);
                }
            }

            // BCC orders mailbox for record-keeping
            $message->bcc(config('emails.orders'));

            return $message;
        }

        $intro = match ($order->status) {
            'confirmed'              => 'Your order has been confirmed and will be processed shortly.',
            'processing'             => 'Your order is now being packed and prepared for dispatch.',
            'shipping to station'    => "Your order is being shipped to **{$order->pickupStation?->name}**." .
                                        ($order->pickupStation?->address ? "\n\nStation address: {$order->pickupStation->address}" : '') .
                                        "\n\nEstimated arrival: " . $order->delivery_window,
            'out for delivery'       => "Your order is on its way! " .
                                        ($order->courier_name ? "It's being delivered by **{$order->courier_name}**" .
                                        ($order->tracking_number ? " (Tracking: {$order->tracking_number})" : '') . '.' : ''),
            'ready for pick up'      => "Your order is ready to be collected from **{$order->pickupStation?->name}**." .
                                        ($order->pickupStation?->instructions ? "\n\n{$order->pickupStation->instructions}" : '') .
                                        "\n\nPlease collect within **4 days**.",
            'cancelled'              => 'Your order has been cancelled. If you paid online, a refund will be processed within 5–7 working days.',
            'pickup window expired'  => "Your order **{$order->reference}** was not collected within the 4-day pickup window." .
                                        "\n\nPlease contact our support team to arrange collection or discuss a refund.",
            default                  => "Your order status has been updated to **{$status}**.",
        };

        $message = (new MailMessage)
            ->subject($subject)
            ->replyTo(config('emails.support'), 'KidsFlairr Support')
            ->greeting("Hello {$notifiable->name},")
            ->line($intro)
            ->line('')
            ->line("**Order Reference:** {$order->reference}")
            ->line("**Status:** {$status}")
            ->line("**Delivery Method:** {$order->getDeliveryMethodLabel()}");

        if ($order->status === 'out for delivery' && $order->tracking_url) {
            $message->action('Track Your Package', $order->tracking_url);
        } else {
            $message->action('View Your Order', url('/account/orders/' . $order->id));
        }

        if ($order->status !== 'delivered' && $order->status !== 'cancelled') {
            $message->line("**Estimated Delivery:** " . $order->delivery_window);
        }

        $message->line('If you have questions, please contact our support team.');

        // CC admins + order processing + customer support on status change notifications to customers
        foreach (NotificationRecipients::adminUsers() as $admin) {
            if ($admin->id !== $notifiable->id) {
                $message->bcc($admin->email, $admin->name);
            }
        }
        foreach (NotificationRecipients::orderProcessingStaff() as $staff) {
            if ($staff->id !== $notifiable->id) {
                $message->bcc($staff->email, $staff->name);
            }
        }
        foreach (NotificationRecipients::customerSupportStaff() as $staff) {
            if ($staff->id !== $notifiable->id) {
                $message->bcc($staff->email, $staff->name);
            }
        }

        // BCC orders mailbox for record-keeping
        $message->bcc(config('emails.orders'));

        return $message;
    }
}
