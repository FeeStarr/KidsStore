<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PickupReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly ?string $message = null,
        public readonly ?string $subject = null,
    ) {
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

        $subject = $this->subject ?? "Reminder: Your order is ready for pickup — {$order->reference}";

        $mail = (new MailMessage)
            ->subject($subject)
            ->replyTo(config('emails.support'), 'KidsFlairr Support')
            ->greeting("Hello {$notifiable->name},")
            ->line("This is a reminder that your order **{$order->reference}** is ready for pickup at **{$stationName}**.");

        if ($stationAddress) {
            $mail->line("**Address:** {$stationAddress}");
        }

        if ($this->message) {
            $mail->line("**{$this->message}**");
        } else {
            $mail->line("Please collect your order within **4 days** to avoid it being marked as expired.");
        }

        $mail->line("Bring a valid ID and your order reference when collecting.")
            ->action('View Order Details', url('/account/orders/' . $order->id))
            ->line('')
            ->line("If you have questions, please contact our support team.");

        // BCC orders mailbox for record-keeping
        $mail->bcc(config('emails.orders'));

        // BCC admins
        foreach (NotificationRecipients::adminUsers() as $admin) {
            $mail->bcc($admin->email, $admin->name);
        }

        return $mail;
    }
}
