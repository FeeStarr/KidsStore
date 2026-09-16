<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryAgentCredentialsNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $email,
        private string $password,
        private string $accountNumber,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $portalUrl = route('delivery-portal.login');

        return (new MailMessage)
            ->subject('Your KidsFlairr Delivery Agent Account')
            ->replyTo(config('emails.support'), 'KidsFlairr Support')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your delivery agent account has been created. Here are your login credentials:')
            ->line('')
            ->line("Account Number: {$this->accountNumber}")
            ->line("Email: {$this->email}")
            ->line("Password: {$this->password}")
            ->line('')
            ->line('Portal URL: ' . $portalUrl)
            ->line('')
            ->line('For security, you will be required to change your password on first login.')
            ->action('Login to Portal', $portalUrl)
            ->line('')
            ->line('If you did not expect this email, please contact support immediately.')
            ->salutation('KidsFlairr Team');
    }
}
