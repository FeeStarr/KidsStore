<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $contactPhone = \App\Models\Setting::get('contact_phone', '');
        $contactEmail = \App\Models\Setting::get('contact_email', '');

        return (new MailMessage)
            ->subject('Welcome to KidsFlairr!')
            ->replyTo(config('emails.support'), 'KidsFlairr Support')
            ->greeting("Hello {$notifiable->name},")
            ->line('Welcome to KidsFlairr! We are thrilled to have you join our community.')
            ->line('Here is what you can do with your account:')
            ->line('- Browse our wide range of kids clothing and accessories')
            ->line('- Place orders and track them in real time')
            ->line('- Create custom frocks tailored to your child')
            ->line('- Get exclusive deals and discounts')
            ->action('Start Shopping', route('shop.home'))
            ->line('')
            ->line('If you ever need help, our support team is just a message away.')
            ->line('')
            ->line("Phone: {$contactPhone}")
            ->line("Email: {$contactEmail}")
            ->salutation('Happy shopping! - The KidsFlairr Team');
    }
}
