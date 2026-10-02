<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountActivationNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Activate your CVMS account')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('You can now activate your account using your registered email at this link:')
            ->action('Activate my account', route('account.activation'))
            ->line('Use your registered email address to receive your activation code and create your password.');
    }
}
