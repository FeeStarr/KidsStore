<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DropoffReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly RefundRequest $refundRequest,
        public readonly string $urgency,   // 'reminder' or 'final'
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
        $deadline = $rr->dropoff_deadline;

        if ($this->urgency === 'final') {
            $subject = "Last day to drop off your return - Return #{$rr->id}";
            $intro = "This is a reminder that <strong>today is the last day</strong> to drop off your return item for Return <strong>#{$rr->id}</strong> ({$scope}). Please bring the item to your assigned pickup station before the end of the day.";
        } else {
            $subject = "Reminder: Drop off your return soon - Return #{$rr->id}";
            $intro = "This is a friendly reminder to drop off your return item for Return <strong>#{$rr->id}</strong> ({$scope}). Please bring the item to your assigned pickup station by <strong>{$deadline->format('M d, Y')}</strong>.";
        }

        $pickupStation = $rr->pickupStation;
        $stationInfo = $pickupStation
            ? "{$pickupStation->name}" . ($pickupStation->address ? " - {$pickupStation->address}" : '')
            : null;

        $mail = (new MailMessage)
            ->subject($subject)
            ->view('emails.dropoff-reminder', [
                'refundRequest' => $rr,
                'order'         => $order,
                'scope'         => $scope,
                'urgency'       => $this->urgency,
                'deadline'      => $deadline,
                'intro'         => $intro,
                'stationInfo'   => $stationInfo,
                'headerColor'   => $this->urgency === 'final' ? '#dc2626' : '#2563eb',
                'headerLabel'   => $this->urgency === 'final' ? 'LAST DAY TO DROP OFF' : 'RETURN DROP-OFF REMINDER',
            ])
            ->from(config('emails.no_reply', config('mail.from.address')), config('mail.from.name'));

        // BCC admin and support
        $admins = NotificationRecipients::adminUsers();
        foreach ($admins as $admin) {
            $mail->bcc($admin->email);
        }

        $support = NotificationRecipients::customerSupportStaff();
        foreach ($support as $staff) {
            $mail->bcc($staff->email);
        }

        return $mail;
    }
}
