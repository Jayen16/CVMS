<?php

namespace App\Console\Commands;

use App\Jobs\SendVaccinationReminder;
use App\Services\ImmediateSmsReminderDemo;
use Illuminate\Console\Command;

class SendTestSms extends Command
{
    protected $signature = 'cvms:send-test-sms
        {--prepare-only : Assign a dummy child due tomorrow without sending or queuing any SMS}';

    protected $description = 'Assign a demo child and immediately send tomorrow\'s vaccination reminder to 09760149526 (uses SMS credits)';

    public function handle(ImmediateSmsReminderDemo $demo): int
    {
        if (! $this->option('prepare-only') && ! in_array(config('reminders.sms.driver'), ['unisms', 'api'], true)) {
            $this->error('Set SMS_DRIVER to unisms or api to send an actual SMS.');

            return self::FAILURE;
        }

        try {
            ['child' => $child, 'parent' => $parent, 'suggestion' => $suggestion] = $demo->prepare();
            $this->info($child->full_name.': '.$suggestion['vaccine_name'].' dose '.$suggestion['dose_number'].' due '.$suggestion['due_at']->format('M d, Y').'.');
            if ($this->option('prepare-only')) {
                $this->info('Dummy child assigned to 09760149526. No SMS sent or queued.');

                return self::SUCCESS;
            }
            // Use the real reminder handler immediately, including delivery history
            // and its protection against sending the same reminder twice.
            $job = new SendVaccinationReminder(
                $child->id, $parent->id, $suggestion['vaccine_name'], $suggestion['dose_number'],
                $suggestion['due_at']->toDateString(), 'sms', 'one_day',
            );
            app()->call([$job, 'handle']);
        } catch (\Throwable $exception) {
            $this->error('SMS request failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Reminder processed for 09760149526. Already-sent reminders are skipped. Handset delivery depends on the provider.');

        return self::SUCCESS;
    }
}
