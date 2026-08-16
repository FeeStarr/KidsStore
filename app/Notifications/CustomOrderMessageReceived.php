<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Str;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOrderMessageReceived extends Notification
{
    use Queueable;

    public function __construct(
        public CustomOrder $customOrder,
        public string $messagePreview
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isAdmin = $notifiable->hasAnyRole(['superadmin', 'admin', 'staff']);
        $sender = $isAdmin ? 'A customer' : 'Our team';

        $mail = (new MailMessage)
            ->subject('New Message for Custom Frock Order')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line($sender . ' has sent a message regarding your custom frock order.')
            ->line('Reference: ' . $this->customOrder->custom_order_number)
            ->line('Message: "' . Str::limit($this->messagePreview, 100) . '"');

        if ($isAdmin) {
            $mail->action('View & Reply', route('admin.custom-orders.show', $this->customOrder));
        } else {
            $mail->action('View & Reply', route('shop.custom-frock.show', $this->customOrder));
        }

        return $mail;
    }
}
