<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentUnderReviewNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly PaymentTransaction $transaction,
        public readonly bool $bccAccounts = true,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Payment Under Review — {$this->order->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A payment for an order requires manual review. The payment result is uncertain.")
            ->line("**Order:** {$this->order->reference}")
            ->line("**Amount:** ₦" . number_format($this->order->grand_total, 2))
            ->line("**Reference:** {$this->transaction->reference}")
            ->line("**Customer:** " . ($this->order->customer?->name ?? 'N/A'))
            ->line("**Reason:** Paystack returned an ambiguous status. The payment may or may not have been received.")
            ->action('Review Payment', route('admin.orders.show', $this->order));

        if ($this->bccAccounts) {
            $message->bcc(config('emails.accounts'));
        }

        return $message;
    }
}
