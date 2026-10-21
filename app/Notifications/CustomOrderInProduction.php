<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use App\Notifications\Concerns\CopiesTheTeam;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOrderInProduction extends Notification
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
            ->subject('Your Custom Order is Now in Production')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Exciting news! Your custom frock is now being crafted.')
            ->line('Reference: '.$this->customOrder->custom_order_number)
            ->line('Our skilled team is working on bringing your design to life.')
            ->action('Track Progress', route('shop.custom-frock.show', $this->customOrder))
            ->line('We\'ll notify you when it\'s ready for quality check.');

        // The customer has a copy of what was promised; so does the team.
        return $this->copyTheTeamOnto($message, $notifiable);

    }
}
