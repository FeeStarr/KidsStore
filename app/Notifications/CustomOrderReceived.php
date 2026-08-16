<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOrderReceived extends Notification
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
            ->subject('Your Custom Frock Request Has Been Received')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('We\'ve received your custom frock request.')
            ->line('Reference: ' . $this->customOrder->custom_order_number)
            ->line('Our team will review your request and get back to you shortly.')
            ->action('View Your Request', route('shop.custom-frock.show', $this->customOrder))
            ->line('Thank you for choosing KidsFlairr!');
    }
}
