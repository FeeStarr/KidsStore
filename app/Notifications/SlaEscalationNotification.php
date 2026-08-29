<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaEscalationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly RefundRequest $refundRequest,
        public readonly string $slaType,   // 'review', 'inspection', or 'dropoff'
        public readonly string $urgency,   // 'warning' or 'breached'
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rr = $this->refundRequest;
        $order = $rr->order;
        $scope = $rr->getScopeLabel();

        $typeLabel = match ($this->slaType) {
            'review'     => 'Return Review',
            'inspection' => 'Item Inspection',
            'dropoff'    => 'Return Drop-off',
            default      => 'SLA',
        };

        $deadline = match ($this->slaType) {
            'review'     => $rr->review_deadline,
            'inspection' => $rr->inspection_deadline,
            'dropoff'    => $rr->dropoff_deadline,
            default      => null,
        };

        if ($this->urgency === 'breached') {
            $subject = "SLA BREACHED: {$typeLabel} overdue - Return #{$rr->id}";
            $intro = "The <strong>{$typeLabel}</strong> SLA for Return <strong>#{$rr->id}</strong> ({$scope}) has been <strong>breached</strong>. Deadline was {$deadline->format('M d, Y')}.";
            $headerColor = '#dc2626';
            $headerLabel = 'SLA BREACHED';
        } else {
            $subject = "SLA Warning: {$typeLabel} due soon - Return #{$rr->id}";
            $intro = "The <strong>{$typeLabel}</strong> SLA for Return <strong>#{$rr->id}</strong> ({$scope}) is due on <strong>{$deadline->format('M d, Y')}</strong>. Please take action soon.";
            $headerColor = '#f59e0b';
            $headerLabel = 'SLA WARNING';
        }

        $mail = (new MailMessage)
            ->subject($subject)
            ->view('emails.sla-escalation', [
                'refundRequest' => $rr,
                'order'         => $order,
                'scope'         => $scope,
                'typeLabel'     => $typeLabel,
                'urgency'       => $this->urgency,
                'deadline'      => $deadline,
                'intro'         => $intro,
                'headerColor'   => $headerColor,
                'headerLabel'   => $headerLabel,
            ])
            ->from(config('emails.no_reply', config('mail.from.address')), config('mail.from.name'));

        // BCC all admin users
        $admins = NotificationRecipients::adminUsers();
        foreach ($admins as $admin) {
            if ($admin->email !== $notifiable->email) {
                $mail->bcc($admin->email);
            }
        }

        // BCC customer support
        $support = NotificationRecipients::customerSupportStaff();
        foreach ($support as $staff) {
            if ($staff->email !== $notifiable->email) {
                $mail->bcc($staff->email);
            }
        }

        return $mail;
    }
}
