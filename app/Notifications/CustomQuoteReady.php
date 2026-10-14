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
        // Derived from the quote's own valid_until, not a fixed window. The admin picks
        // 1-90 days on the quote form (default 7), so hard-coding "24 hours" here told
        // customers the wrong expiry.
        $validity = $this->quote->valid_until
            ? ($this->quote->isExpired()
                ? 'This quote has expired. Please request a new one.'
                : 'This quote is valid until '.$this->quote->valid_until->format('j M Y, g:i a').'.')
            : 'Please review and respond promptly.';

        return (new MailMessage)
            ->subject('Your Custom Request Quote is Ready!')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Great news! We\'ve prepared a quote for your custom frock.')
            ->line('Reference: '.$this->customOrder->custom_order_number)
            ->line('Total Amount: ₦'.number_format($this->quote->total, 2))
            ->line($validity)
            ->action('View & Approve Quote', route('shop.custom-frock.show', $this->customOrder))
            ->line('Please review the breakdown and approve to proceed with production.');
    }
}
