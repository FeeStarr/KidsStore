<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use App\Notifications\NotificationRecipients;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Copies the team onto a customer-facing email.
 *
 * Every customer email in the shop side is copied to the team and to the orders
 * mailbox, so there is a record of what was promised and the customer can see who
 * is dealing with them. The custom request notifications had been left out of that,
 * which meant a quote, a rejection or a cancellation left no trace for the team
 * outside the admin panel.
 *
 * An email that is already addressed to the team is not copied onto itself.
 */
trait CopiesTheTeam
{
    protected function copyTheTeamOnto(MailMessage $message, object $notifiable): MailMessage
    {
        if ($notifiable instanceof User && $notifiable->isAdmin()) {
            return $message;
        }

        foreach (NotificationRecipients::adminUsers() as $admin) {
            $message->bcc($admin->email, $admin->name);
        }

        foreach (NotificationRecipients::orderProcessingStaff() as $staff) {
            $message->bcc($staff->email, $staff->name);
        }

        foreach (NotificationRecipients::customerSupportStaff() as $staff) {
            $message->bcc($staff->email, $staff->name);
        }

        // The orders mailbox keeps a copy for record-keeping.
        $message->bcc(config('emails.orders'));

        return $message;
    }
}
