<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordOtpNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code, public bool $activation = false) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->activation ? 'Your CVMS account activation code' : 'Your CVMS password reset code')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->activation ? 'Use this code to activate your CVMS account:' : 'Use this code to reset your CVMS password:')
            ->line($this->code)
            ->line('This code expires in 10 minutes. If you did not request this, you can ignore this email.');
    }
}
