<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountAccessNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $isSetup = $notifiable->invitation_accepted_at === null;
        $broker = config('auth.defaults.passwords');
        $expiresIn = (int) config("auth.passwords.{$broker}.expire", 60);
        $appName = (string) config('app.name');
        $rhuName = (string) config('rhu.name');
        $shortName = (string) config('rhu.short_name');
        $systemName = (string) config('rhu.system_name');
        $publicUrl = app()->environment('local')
            || (config('system.instance_type') === 'facility' && config('offline.enabled'))
            ? config('app.url')
            : config('app.public_url');

        $actionUrl = rtrim($publicUrl, '/').route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);

        return (new MailMessage)
            ->subject($isSetup ? "Set up your {$shortName} account" : "Reset your {$shortName} password")
            ->view('mail.account-access', [
                'actionUrl' => $actionUrl,
                'appName' => $appName,
                'expiresIn' => $expiresIn,
                'isSetup' => $isSetup,
                'recipientName' => $notifiable->name,
                'rhuName' => $rhuName,
                'shortName' => $shortName,
                'systemName' => $systemName,
            ]);
    }
}
