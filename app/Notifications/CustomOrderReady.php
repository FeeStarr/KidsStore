<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOrderReady extends Notification
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
        $method = $this->customOrder->delivery_method === 'pickup' ? 'for pickup' : 'and will be shipped soon';

        return (new MailMessage)
            ->subject('Your Custom Frock is Ready!')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Great news! Your custom frock has passed quality check and is ready ' . $method . '.')
            ->line('Reference: ' . $this->customOrder->custom_order_number)
            ->action('View Details', route('shop.custom-frock.show', $this->customOrder))
            ->line('Thank you for your patience!');
    }
}
