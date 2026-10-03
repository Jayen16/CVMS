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
        $appName = (string) config('app.name');
        $rhuName = (string) config('rhu.name');
        $shortName = (string) config('rhu.short_name');
        $systemName = (string) config('rhu.system_name');

        return (new MailMessage)
            ->subject("Activate your {$shortName} account")
            ->view('mail.account-activation', [
                'actionUrl' => route('account.activation'),
                'appName' => $appName,
                'recipientName' => $notifiable->name,
                'rhuName' => $rhuName,
                'shortName' => $shortName,
                'systemName' => $systemName,
                'mode' => 'link',
            ]);
    }
}
