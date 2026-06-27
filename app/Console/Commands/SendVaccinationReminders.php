<?php

namespace App\Console\Commands;

use App\Mail\VaccinationDueReminderMail;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationReminder;
use App\Services\ImmunizationSuggestionService;
use App\Services\Sms\SmsGatewayFactory;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendVaccinationReminders extends Command
{
    protected $signature = 'vaccinations:send-reminders {--dry-run : Count reminders without sending messages}';

    protected $description = 'Send due vaccination reminders to linked parents by email and/or SMS.';

    public function handle(ImmunizationSuggestionService $suggestions, SmsGatewayFactory $smsFactory): int
    {
        if (! config('reminders.enabled')) {
            $this->info('Vaccination reminders are disabled.');

            return self::SUCCESS;
        }

        $channels = $this->channels();
        $today = Carbon::now()->startOfDay();
        $latestDueDate = $today->copy()->addDays((int) config('reminders.lookahead_days'));
        $sentCount = 0;
        $skippedCount = 0;

        ChildProfile::query()
            ->with('parents')
            ->whereHas('parents')
            ->orderBy('id')
            ->chunkById(100, function ($children) use ($suggestions, $smsFactory, $channels, $today, $latestDueDate, &$sentCount, &$skippedCount): void {
                foreach ($children as $child) {
                    $suggestion = $suggestions->suggestNextDose($child);

                    if ($suggestion['due_at'] === null || $suggestion['vaccine_name'] === null) {
                        continue;
                    }

                    if ($suggestion['due_at']->startOfDay()->greaterThan($latestDueDate)) {
                        continue;
                    }

                    foreach ($child->parents as $parent) {
                        foreach ($channels as $channel) {
                            if ($this->alreadySent($child, $parent, $suggestion, $channel)) {
                                $skippedCount++;

                                continue;
                            }

                            if ($this->option('dry-run')) {
                                $sentCount++;

                                continue;
                            }

                            $this->sendReminder($child, $parent, $suggestion, $channel, $smsFactory, $today);
                            $sentCount++;
                        }
                    }
                }
            });

        $this->info("Reminder run complete. {$sentCount} queued/sent, {$skippedCount} skipped.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function channels(): array
    {
        $channels = config('reminders.channels');

        if (! is_array($channels)) {
            return ['email'];
        }

        return array_values(array_intersect($channels, ['email', 'sms']));
    }

    /**
     * @param  array{vaccine_code: string|null, vaccine_name: string|null, dose_number: int|null, due_at: Carbon|null, note: string}  $suggestion
     */
    private function alreadySent(ChildProfile $child, User $parent, array $suggestion, string $channel): bool
    {
        return VaccinationReminder::query()
            ->where('child_profile_id', $child->id)
            ->where('parent_id', $parent->id)
            ->where('vaccine_name', $suggestion['vaccine_name'])
            ->where('dose_number', $suggestion['dose_number'])
            ->whereDate('due_at', $suggestion['due_at'])
            ->where('channel', $channel)
            ->where('status', 'sent')
            ->exists();
    }

    /**
     * @param  array{vaccine_code: string|null, vaccine_name: string|null, dose_number: int|null, due_at: Carbon|null, note: string}  $suggestion
     */
    private function sendReminder(ChildProfile $child, User $parent, array $suggestion, string $channel, SmsGatewayFactory $smsFactory, CarbonInterface $today): void
    {
        $reminder = VaccinationReminder::updateOrCreate([
            'child_profile_id' => $child->id,
            'parent_id' => $parent->id,
            'vaccine_name' => $suggestion['vaccine_name'],
            'dose_number' => $suggestion['dose_number'],
            'due_at' => $suggestion['due_at'],
            'channel' => $channel,
        ], [
            'recipient' => 'pending',
            'status' => 'pending',
            'error_message' => null,
        ]);

        try {
            $recipient = $channel === 'sms'
                ? $this->smsRecipient($child, $parent)
                : $parent->email;

            $reminder->update(['recipient' => $recipient]);

            if ($channel === 'sms') {
                $smsFactory->make()->send($recipient, $this->smsMessage($child, $suggestion));
            } else {
                Mail::to($parent->email)->send(new VaccinationDueReminderMail(
                    $child,
                    $suggestion['vaccine_name'],
                    $suggestion['dose_number'],
                    $suggestion['due_at'],
                ));
            }

            $reminder->update([
                'status' => 'sent',
                'sent_at' => $today,
            ]);
        } catch (Throwable $exception) {
            $reminder->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            report($exception);
        }
    }

    private function smsRecipient(ChildProfile $child, User $parent): string
    {
        if (filled($parent->phone)) {
            return $parent->phone;
        }

        if (filled($child->guardian_contact)) {
            return $child->guardian_contact;
        }

        throw new \RuntimeException("No SMS recipient phone number found for parent {$parent->id}.");
    }

    /**
     * @param  array{vaccine_code: string|null, vaccine_name: string|null, dose_number: int|null, due_at: Carbon|null, note: string}  $suggestion
     */
    private function smsMessage(ChildProfile $child, array $suggestion): string
    {
        $dose = $suggestion['dose_number'] ? " dose {$suggestion['dose_number']}" : '';

        return "Reminder: {$child->full_name} is due for {$suggestion['vaccine_name']}{$dose} on {$suggestion['due_at']?->format('M d, Y')}. Please visit the clinic.";
    }
}
