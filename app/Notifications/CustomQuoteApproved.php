<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomQuoteApproved extends Notification
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
            ->subject('Custom Frock Quote Approved - Payment Required')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('The customer has approved the quote for their custom frock.')
            ->line('Reference: ' . $this->customOrder->custom_order_number)
            ->line('Please wait for the payment to be confirmed before starting production.')
            ->action('View Order', route('admin.custom-orders.show', $this->customOrder));
    }
}
