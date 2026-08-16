<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOrderReviewed extends Notification
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
            ->subject('Your Custom Frock Request Has Been Reviewed')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Great news! Your custom frock request has been reviewed and approved for quoting.')
            ->line('Reference: ' . $this->customOrder->custom_order_number)
            ->line('Our team is now preparing a detailed quote for your custom frock. You\'ll receive it shortly.')
            ->action('View Your Request', route('shop.custom-frock.show', $this->customOrder));
    }
}
