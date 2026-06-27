<?php

namespace App\Services\Sms;

use RuntimeException;
use Symfony\Component\Process\Process;

class Sim800cSmsGateway implements SmsGateway
{
    public function send(string $recipient, string $message): void
    {
        $command = config('reminders.sms.sim800c.command');

        if (! is_string($command) || $command === '') {
            throw new RuntimeException('SIM800C_COMMAND is not configured.');
        }

        $process = new Process([
            $command,
            (string) config('reminders.sms.sim800c.port'),
            (string) config('reminders.sms.sim800c.baud_rate'),
            $recipient,
            $message,
        ]);

        $process->setTimeout((int) config('reminders.sms.sim800c.timeout'));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'SIM800C SMS command failed.');
        }
    }
}
