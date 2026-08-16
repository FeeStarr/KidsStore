<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use App\Models\CustomOrderQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomQuoteExpiryReminder extends Notification
{
    use Queueable;

    public function __construct(
        public CustomOrder $customOrder,
        public CustomOrderQuote $quote
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reminder: Your Custom Frock Quote Expires Soon')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('This is a friendly reminder that your custom frock quote will expire in 24 hours.')
            ->line('Reference: ' . $this->customOrder->custom_order_number)
            ->line('Total Amount: ₦' . number_format($this->quote->total, 2))
            ->action('Approve Now', route('shop.custom-frock.show', $this->customOrder))
            ->line('If you don\'t approve before expiry, you\'ll need to request a new quote.');
    }
}
