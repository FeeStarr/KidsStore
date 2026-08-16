<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOrderInfoRequested extends Notification
{
    use Queueable;

    public function __construct(
        public CustomOrder $customOrder,
        public ?string $message = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('More Information Needed for Your Custom Frock')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('We need a bit more information to proceed with your custom frock request.')
            ->line('Reference: ' . $this->customOrder->custom_order_number);

        if ($this->message) {
            $mail->line('Message from our team: ' . $this->message);
        }

        return $mail
            ->action('View & Respond', route('shop.custom-frock.show', $this->customOrder))
            ->line('Please respond as soon as possible so we can continue working on your order.');
    }
}
