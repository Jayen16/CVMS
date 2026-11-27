<?php

namespace App\Console\Commands;

use App\Jobs\SendVaccinationReminder;
use App\Models\ChildProfile;
use App\Models\SystemInstallation;
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
        $queuedCount = 0;
        $skippedCount = 0;

        $children = ChildProfile::query()
            ->with('parents')
            ->whereHas('parents')
            ->orderBy('id');

        if (config('system.instance_type') === 'central') {
            // Facility-owned children are synchronized to central with the
            // owning facility UUID. Central only reminds its own records.
            $children->whereNull('facility_uuid');
        } else {
            $installation = SystemInstallation::query()->first();

            if ($installation?->status !== 'active' || blank($installation->barangay_id)) {
                $this->info('Vaccination reminders skipped: facility installation is not active or has no assigned barangay.');

                return self::SUCCESS;
            }

            // A facility may remind only children registered in its assigned
            // barangay. Facility-owned child rows are normally local and may
            // not have facility_uuid populated yet.
            $children->where('barangay_id', $installation->barangay_id);
        }

        $children->chunkById(100, function ($children) use ($suggestions, $channels, $today, &$queuedCount, &$skippedCount): void {
            foreach ($children as $child) {
                $suggestion = $suggestions->suggestNextDose($child);

                if ($suggestion['due_at'] === null || $suggestion['vaccine_name'] === null) {
                    continue;
                }

                $dueAt = $suggestion['due_at']->copy()->startOfDay();
                $daysUntilDue = (int) $today->diffInDays($dueAt, false);
                $stage = array_search($daysUntilDue, SendVaccinationReminder::STAGE_DAYS, true);
                if ($stage === false) {
                    continue;
                }

                foreach ($child->parents as $parent) {
                    foreach ($this->availableChannels($parent, $channels) as $channel) {
                        if ($this->alreadySent($child, $parent, $suggestion, $channel, $stage)) {
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
                                $stage,
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
    private function availableChannels(User $parent, array $channels): array
    {
        if (in_array('sms', $channels, true) && filled($parent->phone)) {
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
    private function alreadySent(ChildProfile $child, User $parent, array $suggestion, string $channel, string $stage): bool
    {
        return VaccinationReminder::query()
            ->where('child_profile_id', $child->id)
            ->where('parent_id', $parent->id)
            ->where('vaccine_name', $suggestion['vaccine_name'])
            ->where('dose_number', $suggestion['dose_number'])
            ->whereDate('due_at', $suggestion['due_at'])
            ->where('channel', $channel)
            ->where('reminder_stage', $stage)
            ->where('status', 'sent')
            ->exists();
    }
}
