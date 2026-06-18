<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a refund request is approved, rejected, or successfully refunded.
 *
 * TO:  Customer
 * BCC: Admin + superadmin
 */
class RefundStatusNotification extends Notification
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

        [$subject, $intro, $detail] = match ($rr->status) {
            'approved' => [
                "Refund approved for order {$order->reference}",
                "Your refund request has been **approved**.",
                "The refund of **{$amount}** for _{$scope}_ will be credited to your original payment method within **5–7 working days**.",
            ],
            'refunded' => [
                "Refund processed for order {$order->reference}",
                "Your refund has been **processed successfully**.",
                "**{$amount}** for _{$scope}_ has been returned to your original payment method. It may take 1–3 business days to appear.",
            ],
            'rejected' => [
                "Refund request update for order {$order->reference}",
                "Unfortunately, your refund request has been **declined**.",
                "**Reason:** " . ($rr->admin_note ?: 'Please contact support for more information.'),
            ],
            default => [
                "Refund update for order {$order->reference}",
                "Your refund request has been updated.",
                "Status: **" . ucfirst($rr->status) . "**",
            ],
        };

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($intro)
            ->line($detail)
            ->line('')
            ->line("**Order Reference:** {$order->reference}")
            ->line("**Refund scope:** {$scope}")
            ->line("**Amount:** {$amount}")
            ->action('View Your Order', url('/account/orders/' . $order->id))
            ->line('If you have questions, please contact our support team.');

        // BCC admins
        foreach (NotificationRecipients::adminUsers() as $admin) {
            if ($admin->id !== $notifiable->id) {
                $message->bcc($admin->email, $admin->name);
            }
        }

        return $message;
    }
}
