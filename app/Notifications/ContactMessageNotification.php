<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ContactMessage $message)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $msg = $this->message;

        return (new MailMessage)
            ->subject("New Contact Message — {$msg->subject}")
            ->bcc(config('emails.support'))
            ->markdown('emails.contact-message', ['message' => $msg]);
    }
}
