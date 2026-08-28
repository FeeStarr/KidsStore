<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestOtpNotification extends Notification
{
    public function __construct(
        private string $code,
        private int $expiryMinutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your email - KidsFlairr')
            ->greeting('Verify your email')
            ->line('We\'ve sent a 6-digit verification code to complete your order.')
            ->line('Your verification code:')
            ->line("**{$this->code}**")
            ->line("This code expires in {$this->expiryMinutes} minutes.")
            ->line('If you didn\'t request this, you can safely ignore this email.')
            ->salutation('Happy shopping! - The KidsFlairr Team');
    }
}
