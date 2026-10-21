<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use App\Notifications\Concerns\CopiesTheTeam;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomQuoteExpired extends Notification
{
    use CopiesTheTeam;
    use Queueable;

    public function __construct(
        public CustomOrder $customOrder
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your Custom Request Quote Has Expired')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('The quote we prepared for your custom frock has expired.')
            ->line('Reference: '.$this->customOrder->custom_order_number)
            ->line('No worries! You can submit a new request and we will prepare a fresh quote for you.')
            ->action('Start a New Request', route('shop.custom-frock.create'))
            ->line('If you have any questions, our support team is here to help.');

        // The customer has a copy of what was promised; so does the team.
        return $this->copyTheTeamOnto($message, $notifiable);

    }
}
