<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\PaymentVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentVerificationRejectedNotification extends Notification
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
        $mail = (new MailMessage)
            ->subject("Payment Verification Failed — {$this->order->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Payment for order **{$this->order->reference}** was **not verified** by the admin.")
            ->line("**Amount:** ₦" . number_format($this->order->grand_total, 2));

        if ($this->verification->admin_note) {
            $mail->line("**Reason:** {$this->verification->admin_note}");
        }

        $mail->line("Please ask the customer to retry or try a different payment method.")
            ->action('View Order', route('pickup-portal.dashboard', ['filter' => 'ready']));

        return $mail;
    }
}
