<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a pickup station when a return is approved and assigned to them for collection.
 *
 * TO: Station email
 */
class RefundReturnNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly RefundRequest $refundRequest)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rr    = $this->refundRequest;
        $order = $rr->order;
        $scope = $rr->getScopeLabel();
        $amount = '₦' . number_format($rr->amount, 2);

        $subject = "Return approved for collection - {$order->reference}";

        $customerName = $order->customer?->name ?? 'N/A';

        $message = (new MailMessage)
            ->subject($subject)
            ->bcc(config('emails.stations'))
            ->greeting("Hello {$notifiable->name},")
            ->line("A return request has been **approved** and assigned to your station for collection.")
            ->line('')
            ->line("**Order Reference:** {$order->reference}")
            ->line("**Customer:** {$customerName}")
            ->line("**Item:** {$scope}")
            ->line("**Return Reason:** {$rr->reason_label}")
            ->line("**Refund Amount:** {$amount}")
            ->line('')
            ->line("When the customer brings the item to your station, please mark it as **collected** in your portal.")
            ->line("**Important:** Only collect the item if the customer's payment status is **paid**.")
            ->action('View Return Details', url('/pickup-portal'));

        // BCC admins
        foreach (NotificationRecipients::adminUsers() as $admin) {
            $message->bcc($admin->email, $admin->name);
        }

        return $message;
    }
}
