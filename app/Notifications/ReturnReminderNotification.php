<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReturnReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly RefundRequest $return, public readonly ?string $message = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $return = $this->return;
        $order = $return->order;
        $station = $return->pickupStation;
        $stationName = $station?->name ?? 'our pickup station';
        $stationAddress = $station?->address ?? '';
        $itemName = $return->orderItem?->product?->name ?? 'your item';

        $mail = (new MailMessage)
            ->subject("Reminder: Please return item for order {$order->reference}")
            ->replyTo(config('emails.support'), 'KidsFlairr Support')
            ->greeting("Hello {$notifiable->name},")
            ->line("This is a friendly reminder that your return for **{$itemName}** in order **{$order->reference}** has been approved.")
            ->line("Please bring the item to **{$stationName}** at your earliest convenience.");

        if ($stationAddress) {
            $mail->line("**Address:** {$stationAddress}");
        }

        $mail->line("Please ensure the item is in its original condition with all tags attached.")
            ->action('View Return Details', url('/account/orders/' . $order->id));

        if ($this->message) {
            $mail->line('');
            $mail->line("**Message from station:** {$this->message}");
        }

        $mail->line('')
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
