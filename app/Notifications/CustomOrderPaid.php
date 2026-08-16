<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOrderPaid extends Notification
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
        return (new MailMessage)
            ->subject('Payment Confirmed for Your Custom Frock')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your payment has been confirmed!')
            ->line('Reference: ' . $this->customOrder->custom_order_number)
            ->line('Amount Paid: ₦' . number_format($this->customOrder->amount_paid, 2))
            ->line('Our team will now begin working on your custom frock.')
            ->action('Track Your Order', route('shop.custom-frock.show', $this->customOrder))
            ->line('You\'ll receive updates as your order progresses through production.');
    }
}
