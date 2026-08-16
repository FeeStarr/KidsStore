<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOrderCompleted extends Notification
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
            ->subject('Your Custom Frock Has Been Delivered')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your custom frock has been delivered!')
            ->line('Reference: ' . $this->customOrder->custom_order_number)
            ->line('We hope you love your new frock!')
            ->action('Leave a Review', route('shop.custom-frock.show', $this->customOrder))
            ->line('Thank you for choosing KidsFlairr. We look forward to serving you again!');
    }
}
