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

        $message = (new MailMessage)
            ->subject("Your order is ready for pickup - {$order->reference}")
            ->replyTo(config('emails.support'), 'KidsFlairr Support')
            ->view('emails.order-ready-for-pickup', ['order' => $order]);

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
