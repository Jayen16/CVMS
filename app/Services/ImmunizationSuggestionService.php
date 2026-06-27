<?php

namespace App\Services;

use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Models\VaccineSchedule;
use App\Models\VaccineType;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;

class ImmunizationSuggestionService
{
    /**
     * @return array{vaccine_code: string|null, vaccine_name: string|null, dose_number: int|null, due_at: Carbon|null, action_at: Carbon|null, status: string, due_label: string|null, note: string, checks: list<string>}
     */
    public function suggestNextDose(ChildProfile $child): array
    {
        $records = VaccinationRecord::query()
            ->where('child_profile_id', $child->id)
            ->where('verification_status', '!=', 'rejected')
            ->with('vaccineType')
            ->get();

        $candidate = $this->nextMissingRoutineDose($child, $records);

        if ($candidate === null) {
            return [
                'vaccine_code' => null,
                'vaccine_name' => null,
                'dose_number' => null,
                'due_at' => null,
                'action_at' => null,
                'status' => 'complete',
                'due_label' => null,
                'note' => 'No pending routine dose found from the configured PIDSP 2026 schedule. Review the child record for catch-up or special-risk indications.',
                'checks' => [
                    'Review catch-up guidance for children with incomplete or uncertain history.',
                    'Check contraindications and special-risk indications before vaccination.',
                ],
            ];
        }

        $today = Carbon::today();
        $isOverdue = $candidate['due_at']->lt($today);
        $actionDate = $isOverdue ? $today : $candidate['due_at'];
        $status = $isOverdue ? 'overdue' : 'upcoming';
        $timingNote = $isOverdue
            ? "This dose is overdue from {$candidate['due_at']->format('M d, Y')}; suggested action date is {$actionDate->format('M d, Y')}."
            : "Suggested action date is {$actionDate->format('M d, Y')}.";

        return [
            'vaccine_code' => $candidate['code'],
            'vaccine_name' => $candidate['name'],
            'dose_number' => $candidate['dose'],
            'due_at' => $candidate['due_at'],
            'action_at' => $actionDate,
            'status' => $status,
            'due_label' => $candidate['label'],
            'note' => "Suggested {$candidate['name']} dose {$candidate['dose']} due {$candidate['label']} based on the configured PIDSP 2026 routine schedule. {$timingNote} Use clinical judgment for contraindications, catch-up rules, minimum intervals, and stock availability.",
            'checks' => [
                'Confirm the previous dose history and parent-submitted records.',
                'Check minimum age and interval rules before giving the dose.',
                'Screen for contraindications, precautions, illness, and allergy history.',
                'Confirm vaccine stock and document the administered date after vaccination.',
            ],
        ];
    }

    /**
     * @return array{next_due_at: Carbon|null, suggested_vaccine: string|null, suggestion_note: string}
     */
    public function suggestionForRecord(ChildProfile $child): array
    {
        $suggestion = $this->suggestNextDose($child);

        return [
            'next_due_at' => $suggestion['due_at'],
            'suggested_vaccine' => $suggestion['vaccine_name'],
            'suggestion_note' => $suggestion['note'],
        ];
    }

    /**
     * @param  EloquentCollection<int, VaccinationRecord>  $records
     * @return array{code: string, name: string, dose: int, due_at: Carbon, label: string}|null
     */
    private function nextMissingRoutineDose(ChildProfile $child, EloquentCollection $records): ?array
    {
        $birthdate = Carbon::parse($child->birthdate)->startOfDay();
        $vaccineNames = VaccineType::query()->pluck('name', 'code');
        $recordedDoseNumbers = [];

        foreach ($records as $record) {
            if ($record->vaccineType === null || $record->dose_number === null) {
                continue;
            }

            $recordedDoseNumbers[$record->vaccineType->code][] = (int) $record->dose_number;
        }

        $candidates = [];
        $routineSchedule = VaccineSchedule::query()
            ->where('active', true)
            ->with('vaccineType')
            ->orderBy('vaccine_type_id')
            ->orderBy('dose_number')
            ->get();

        foreach ($routineSchedule as $dose) {
            if ($dose->vaccineType === null) {
                continue;
            }

            $code = $dose->vaccineType->code;
            $doseNumber = (int) $dose->dose_number;

            if (in_array($doseNumber, $recordedDoseNumbers[$code] ?? [], true)) {
                continue;
            }

            $candidates[] = [
                'code' => $code,
                'name' => $vaccineNames[$code] ?? $dose->vaccineType->name,
                'dose' => $doseNumber,
                'due_at' => $dose->dueDateFromBirthdate($birthdate),
                'label' => $dose->label,
            ];
        }

        usort($candidates, fn (array $first, array $second) => $first['due_at']->timestamp <=> $second['due_at']->timestamp);

        return $candidates[0] ?? null;
    }
}
