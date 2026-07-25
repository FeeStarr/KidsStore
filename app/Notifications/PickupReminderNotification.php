<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PickupReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Order $order, public readonly ?string $message = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $station = $order->pickupStation;
        $stationName = $station?->name ?? 'our pickup station';
        $stationAddress = $station?->address ?? '';

        $mail = (new MailMessage)
            ->subject("Reminder: Your order is ready for pickup — {$order->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("This is a friendly reminder that your order **{$order->reference}** is ready for pickup at **{$stationName}**.");

        if ($stationAddress) {
            $mail->line("**Address:** {$stationAddress}");
        }

        $mail->line("Please collect your order within **7 days** to avoid it being returned.")
            ->line("Bring a valid ID and your order reference when collecting.")
            ->action('View Order Details', url('/account/orders/' . $order->id));

        if ($this->message) {
            $mail->line('');
            $mail->line("**Message from station:** {$this->message}");
        }

        $mail->line('')
            ->line("If you have questions, please contact our support team.");

        return $mail;
    }
}
