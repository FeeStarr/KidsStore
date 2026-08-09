<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
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

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $rr    = $this->refundRequest;
        $order = $rr->order;

        $rr->loadMissing('orderItem.product', 'orderItem.variant.image', 'orderItem.variant.images');

        $message = (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($this->getSubject())
            ->replyTo(config('emails.support'), 'KidsFlairr Support')
            ->view('emails.refund-status', [
                'refundRequest' => $rr,
                'order'         => $order,
                'notifiable'    => $notifiable,
            ]);

        // BCC admins
        foreach (NotificationRecipients::adminUsers() as $admin) {
            if ($admin->id !== $notifiable->id) {
                $message->bcc($admin->email, $admin->name);
            }
        }

        // BCC accounts mailbox only when sending to customer (avoid duplicates)
        if (! $notifiable->isAdmin() && ! $notifiable->isStaff()) {
            $message->bcc(config('emails.accounts'));
        }

        return $message;
    }

    private function getSubject(): string
    {
        $order = $this->refundRequest->order;

        return match ($this->refundRequest->status) {
            RefundRequest::STATUS_REQUESTED            => "Return request received — {$order->reference}",
            RefundRequest::STATUS_AWAITING_EVIDENCE    => "Evidence needed for your return — {$order->reference}",
            RefundRequest::STATUS_APPROVED             => "Return approved — {$order->reference}",
            RefundRequest::STATUS_REJECTED             => "Return request update — {$order->reference}",
            RefundRequest::STATUS_RECEIVED             => "Item received — {$order->reference}",
            RefundRequest::STATUS_REFUND_APPROVED,
            RefundRequest::STATUS_REFUND_PROCESSING    => "Refund approved — {$order->reference}",
            RefundRequest::STATUS_REFUNDED             => "Refund completed — {$order->reference}",
            RefundRequest::STATUS_REPLACEMENT_APPROVED => "Replacement approved — {$order->reference}",
            RefundRequest::STATUS_REPLACEMENT_SHIPPED  => "Replacement shipped — {$order->reference}",
            RefundRequest::STATUS_CANCELLED            => "Return request cancelled — {$order->reference}",
            RefundRequest::STATUS_RETURN_COLLECTED     => "Return item collected — {$order->reference}",
            default                                    => "Return update — {$order->reference}",
        };
    }
}
