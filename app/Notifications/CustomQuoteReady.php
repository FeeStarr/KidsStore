<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use App\Models\CustomOrderQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomQuoteReady extends Notification
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
            ->subject('Your Custom Frock Quote is Ready!')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Great news! We\'ve prepared a quote for your custom frock.')
            ->line('Reference: ' . $this->customOrder->custom_order_number)
            ->line('Total Amount: ₦' . number_format($this->quote->total, 2))
            ->line('This quote is valid for 24 hours.')
            ->action('View & Approve Quote', route('shop.custom-frock.show', $this->customOrder))
            ->line('Please review the breakdown and approve to proceed with production.');
    }
}
