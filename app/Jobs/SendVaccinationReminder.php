<?php

namespace App\Jobs;

use App\Mail\VaccinationDueReminderMail;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationReminder;
use App\Services\InAppNotificationService;
use App\Services\Sms\SmsGatewayFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendVaccinationReminder implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public const STAGE_DAYS = ['initial' => 7, 'three_days' => 3, 'one_day' => 1];

    public function __construct(
        public string $childId,
        public string $parentId,
        public string $vaccineName,
        public ?int $doseNumber,
        public string $dueAt,
        public string $channel,
        public string $reminderStage = 'initial',
    ) {}

    public function uniqueId(): string
    {
        return implode(':', [$this->childId, $this->parentId, $this->vaccineName, $this->doseNumber ?? 'none', $this->dueAt, $this->channel, $this->reminderStage]);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(SmsGatewayFactory $smsFactory, InAppNotificationService $notifications): void
    {
        $child = ChildProfile::query()->find($this->childId);
        $parent = User::query()->find($this->parentId);

        if ($child === null || $parent === null) {
            return;
        }

        $dueAt = Carbon::parse($this->dueAt)->startOfDay();
        // Recheck the exact stage at delivery time, including retries and
        // legacy jobs. Never send a missed stage on a later day.
        $daysUntilDue = (int) Carbon::today()->diffInDays($dueAt, false);
        if ((self::STAGE_DAYS[$this->reminderStage] ?? null) !== $daysUntilDue) {
            return;
        }

        $reminder = VaccinationReminder::firstOrCreate([
            'child_profile_id' => $child->id,
            'parent_id' => $parent->id,
            'vaccine_name' => $this->vaccineName,
            'dose_number' => $this->doseNumber,
            'due_at' => $dueAt,
            'channel' => $this->channel,
            'reminder_stage' => $this->reminderStage,
        ], [
            'recipient' => 'pending',
            'status' => 'pending',
            'error_message' => null,
        ]);

        if ($reminder->status === 'sent') {
            return;
        }

        try {
            $recipient = $this->channel === 'sms'
                ? $this->smsRecipient($parent)
                : $parent->email;

            $reminder->update(['recipient' => $recipient]);

            if ($this->channel === 'sms') {
                $smsFactory->make()->send($recipient, $this->message($child, $dueAt));
            } else {
                Mail::to($parent->email)->send(new VaccinationDueReminderMail(
                    $child,
                    $this->vaccineName,
                    $this->doseNumber,
                    $dueAt,
                ));
            }

            $reminder->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
            $notifications->vaccinationDue(
                $parent,
                'vaccination-due:'.$child->id.':'.$this->vaccineName.':'.$this->doseNumber.':'.$dueAt->toDateString().':'.$this->reminderStage,
                $this->message($child, $dueAt),
                route('children.show', $child),
            );
        } catch (Throwable $exception) {
            $reminder->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function smsRecipient(User $parent): string
    {
        if (filled($parent->phone)) {
            return User::smsRecipient($parent->phone);
        }

        throw new \RuntimeException("No SMS recipient phone number found for parent {$parent->id}.");
    }

    private function message(ChildProfile $child, Carbon $dueAt): string
    {
        $dose = $this->doseNumber ? " dose {$this->doseNumber}" : '';

        return "Reminder: {$child->full_name} is due for {$this->vaccineName}{$dose} on {$dueAt->format('M d, Y')}. Please visit the clinic.";
    }
}
