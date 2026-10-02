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
        $appName = (string) config('app.name');
        $rhuName = (string) config('rhu.name');
        $shortName = (string) config('rhu.short_name');
        $systemName = (string) config('rhu.system_name');

        return (new MailMessage)
            ->subject($this->activation ? "Your {$shortName} account activation code" : "Your {$shortName} password reset code")
            ->view('mail.account-activation', [
                'appName' => $appName,
                'code' => $this->code,
                'recipientName' => $notifiable->name,
                'rhuName' => $rhuName,
                'shortName' => $shortName,
                'systemName' => $systemName,
                'mode' => $this->activation ? 'code' : 'reset-code',
            ]);
    }
}
