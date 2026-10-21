<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use App\Notifications\Concerns\CopiesTheTeam;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOrderShipped extends Notification
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
            ->subject('Your Custom Order Has Been Shipped')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your custom frock is on its way!')
            ->line('Reference: '.$this->customOrder->custom_order_number)
            ->line('Tracking Number: '.($this->customOrder->tracking_number ?? 'N/A'))
            ->line('Courier: '.($this->customOrder->courier_name ?? 'N/A'))
            ->action('Track Shipment', route('shop.custom-frock.show', $this->customOrder))
            ->line('Thank you for choosing KidsFlairr!');

        // The customer has a copy of what was promised; so does the team.
        return $this->copyTheTeamOnto($message, $notifiable);

    }
}
