<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsGatewayFactory;
use Illuminate\Console\Command;

class SendCosteloSms extends Command
{
    protected $signature = 'cvms:send-costelo-sms';

    protected $description = 'Immediately send the account invitation SMS to +639175450627 (uses SMS credits)';

    public function handle(SmsGatewayFactory $gateways): int
    {
        if (! in_array(config('reminders.sms.driver'), ['unisms', 'api'], true)) {
            $this->error('Set SMS_DRIVER to unisms or api to send an actual SMS.');

            return self::FAILURE;
        }

        try {
            $gateways->make()->send(
                '+639175450627',
                'CVMS: You are invited to log in to your account using the default password as your temporary password.',
            );
        } catch (\Throwable $exception) {
            $this->error('SMS request failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('SMS submitted to the gateway for +639175450627. Handset delivery depends on the provider.');

        return self::SUCCESS;
    }
}
