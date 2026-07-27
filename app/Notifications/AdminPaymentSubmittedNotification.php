<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\PaymentVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminPaymentSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly PaymentVerification $verification,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $station = $this->order->pickupStation;
        $stationName = $station?->name ?? 'Unknown Station';

        return (new MailMessage)
            ->subject("Payment Verification Required — {$this->order->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A pickup station has submitted a payment verification request.")
            ->line("**Order:** {$this->order->reference}")
            ->line("**Station:** {$stationName}")
            ->line("**Amount:** ₦" . number_format($this->order->grand_total, 2))
            ->line("**Submitted:** {$this->verification->submitted_at->format('M d, Y H:i')}")
            ->action('Verify Payment', route('admin.orders.show', $this->order));
    }
}
