<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a return request status changes.
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
            RefundRequest::STATUS_REQUESTED => [
                "Return request received — {$order->reference}",
                "We've received your return request.",
                "Your request for _{$scope}_ is now under review. We'll get back to you within 1–2 business days.",
            ],
            RefundRequest::STATUS_AWAITING_EVIDENCE => [
                "Evidence needed for your return — {$order->reference}",
                "We need additional evidence to process your return.",
                "Please upload the required photos/videos for _{$scope}_ to continue. You can do this from your order page.",
            ],
            RefundRequest::STATUS_APPROVED => [
                "Return approved — {$order->reference}",
                "Your return request has been **approved**.",
                "Please ship the item back to us. Once we receive and inspect it, your **{$amount}** refund will be processed within 5–7 working days.",
            ],
            RefundRequest::STATUS_REJECTED => [
                "Return request update — {$order->reference}",
                "Unfortunately, your return request has been **declined**.",
                "**Reason:** " . ($rr->admin_note ?: 'Please contact support for more information.'),
            ],
            RefundRequest::STATUS_RECEIVED => [
                "Item received — {$order->reference}",
                "We've received your returned item.",
                "Our team is inspecting _{$scope}_. You'll receive an update once the inspection is complete.",
            ],
            RefundRequest::STATUS_REFUND_APPROVED, RefundRequest::STATUS_REFUND_PROCESSING => [
                "Refund approved — {$order->reference}",
                "Your refund has been approved and is being processed.",
                "**{$amount}** for _{$scope}_ will be credited to your original payment method within 5–7 working days.",
            ],
            RefundRequest::STATUS_REFUNDED => [
                "Refund completed — {$order->reference}",
                "Your refund has been **processed successfully**.",
                "**{$amount}** for _{$scope}_ has been returned to your original payment method. It may take 1–3 business days to appear.",
            ],
            RefundRequest::STATUS_REPLACEMENT_APPROVED => [
                "Replacement approved — {$order->reference}",
                "Your replacement request has been **approved**.",
                "A replacement for _{$scope}_ will be shipped to you shortly.",
            ],
            RefundRequest::STATUS_REPLACEMENT_SHIPPED => [
                "Replacement shipped — {$order->reference}",
                "Your replacement has been **shipped**.",
                "Your replacement for _{$scope}_ is on its way. You'll receive it soon.",
            ],
            RefundRequest::STATUS_CANCELLED => [
                "Return request cancelled — {$order->reference}",
                "Your return request has been **cancelled**.",
                "If this was a mistake, you can submit a new request within the return window.",
            ],
            RefundRequest::STATUS_RETURN_COLLECTED => [
                "Return item collected — {$order->reference}",
                "The returned item for _{$scope}_ has been **collected at the pickup station**.",
                "Our team will inspect the item and process your **{$amount}** refund shortly.",
            ],
            default => [
                "Return update — {$order->reference}",
                "Your return request has been updated.",
                "Status: **{$rr->status_label}**",
            ],
        };

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($intro)
            ->line($detail)
            ->line('')
            ->line("**Order Reference:** {$order->reference}")
            ->line("**Item:** {$scope}")
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
