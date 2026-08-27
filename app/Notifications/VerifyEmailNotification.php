<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification
{

    public function __construct(private int $userId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('shop.verification.verify', [
            'id' => $this->userId,
            'hash' => sha1($notifiable->email),
        ], true);

        return (new MailMessage)
            ->subject('Verify your email address - KidsFlairr')
            ->greeting('Welcome to KidsFlairr!')
            ->line('Thank you for creating an account. Please click the button below to verify your email address.')
            ->action('Verify Email Address', $url)
            ->line('This verification link will expire in 60 minutes.')
            ->line('If you did not create an account, no further action is required.')
            ->salutation('Happy shopping! - The KidsFlairr Team');
    }
}
