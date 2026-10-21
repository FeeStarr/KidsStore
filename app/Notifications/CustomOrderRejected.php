<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use App\Notifications\Concerns\CopiesTheTeam;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOrderRejected extends Notification
{
    use CopiesTheTeam;
    use Queueable;

    public function __construct(
        public CustomOrder $customOrder,
        public ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Update on Your Custom Request')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Unfortunately we are unable to take on your custom frock request at this time.')
            ->line('Reference: '.$this->customOrder->custom_order_number);

        if ($this->reason) {
            $mail->line('Reason: '.$this->reason);
        }

        return $mail->action('View Details', route('shop.custom-frock.show', $this->customOrder))
            ->line('You are welcome to submit a new request, and our support team is happy to help with any questions.');

        // The customer has a copy of what was promised; so does the team.
        return $this->copyTheTeamOnto($message, $notifiable);

    }
}
