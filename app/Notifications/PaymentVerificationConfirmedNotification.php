<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\PaymentVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentVerificationConfirmedNotification extends Notification
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
        return (new MailMessage)
            ->subject("Payment Confirmed — {$this->order->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Payment for order **{$this->order->reference}** has been confirmed by an admin.")
            ->line("**Amount:** ₦" . number_format($this->order->grand_total, 2))
            ->line("You can now release the order to the customer.")
            ->line("**Verified:** {$this->verification->reviewed_at->format('M d, Y H:i')}")
            ->action('View Order', route('pickup-portal.dashboard', ['filter' => 'ready']));
    }
}
