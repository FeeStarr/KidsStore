<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOrderCancelled extends Notification
{
    use Queueable;

    public function __construct(
        public CustomOrder $customOrder
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isAdmin = $notifiable->hasAnyRole(['superadmin', 'admin', 'staff']);

        $mail = (new MailMessage)
            ->subject('Custom Frock Order Cancelled')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A custom frock order has been cancelled.')
            ->line('Reference: ' . $this->customOrder->custom_order_number);

        if ($isAdmin) {
            $mail->action('View Order', route('admin.custom-orders.show', $this->customOrder));
        } else {
            $mail->action('View Details', route('shop.custom-frock.show', $this->customOrder))
                 ->line('If you have any questions, please contact our support team.');
        }

        return $mail;
    }
}
