<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\PaymentVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentVerificationDelayedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly PaymentVerification $verification,
        public readonly bool $bccAccounts = true,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = $this->verification->getMinutesElapsed();
        $station = $this->order->pickupStation;
        $stationName = $station?->name ?? 'Unknown Station';

        $message = (new MailMessage)
            ->subject("⚠ Payment Verification Overdue - {$this->order->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A payment verification request has been waiting for **{$minutes} minutes** without admin action.")
            ->line("**Order:** {$this->order->reference}")
            ->line("**Station:** {$stationName}")
            ->line("**Amount:** ₦" . number_format($this->order->grand_total, 2))
            ->line("**Submitted:** {$this->verification->submitted_at->format('M d, Y H:i')}")
            ->action('Verify Now', route('admin.orders.show', $this->order));

        if ($this->bccAccounts) {
            $message->bcc(config('emails.accounts'));
        }

        return $message;
    }
}
