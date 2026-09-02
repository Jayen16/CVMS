<?php

namespace App\Console\Commands;

use App\Jobs\SendVaccinationReminder;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationReminder;
use App\Services\ImmunizationSuggestionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendVaccinationReminders extends Command
{
    protected $signature = 'vaccinations:send-reminders {--dry-run : Count reminders without dispatching jobs}';

    protected $description = 'Queue due vaccination reminders to linked parents by email and/or SMS.';

    public function handle(ImmunizationSuggestionService $suggestions): int
    {
        if (! config('reminders.enabled')) {
            $this->info('Vaccination reminders are disabled.');

            return self::SUCCESS;
        }

        $channels = $this->channels();
        $today = Carbon::now()->startOfDay();
        $latestDueDate = $today->copy()->addDays((int) config('reminders.lookahead_days'));
        $queuedCount = 0;
        $skippedCount = 0;

        ChildProfile::query()
            ->with('parents')
            ->whereHas('parents')
            ->orderBy('id')
            ->chunkById(100, function ($children) use ($suggestions, $channels, $latestDueDate, &$queuedCount, &$skippedCount): void {
                foreach ($children as $child) {
                    $suggestion = $suggestions->suggestNextDose($child);

                    if ($suggestion['due_at'] === null || $suggestion['vaccine_name'] === null) {
                        continue;
                    }

                    if ($suggestion['due_at']->startOfDay()->greaterThan($latestDueDate)) {
                        continue;
                    }

                    foreach ($child->parents as $parent) {
                        foreach ($this->availableChannels($child, $parent, $channels) as $channel) {
                            if ($this->alreadySent($child, $parent, $suggestion, $channel)) {
                                $skippedCount++;

                                continue;
                            }

                            $queuedCount++;

                            if (! $this->option('dry-run')) {
                                SendVaccinationReminder::dispatch(
                                    (string) $child->id,
                                    (string) $parent->id,
                                    $suggestion['vaccine_name'],
                                    $suggestion['dose_number'],
                                    $suggestion['due_at']->toDateString(),
                                    $channel,
                                );
                            }
                        }
                    }
                }
            });

        $this->info("Reminder run complete. {$queuedCount} queued, {$skippedCount} skipped.");

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
     * @param  list<string>  $channels
     * @return list<string>
     */
    private function availableChannels(ChildProfile $child, User $parent, array $channels): array
    {
        if (in_array('sms', $channels, true) && (filled($parent->phone) || filled($child->guardian_contact))) {
            return ['sms'];
        }

        if (in_array('email', $channels, true) && filled($parent->email)) {
            return ['email'];
        }

        return [];
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

}
