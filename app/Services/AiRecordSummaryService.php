<?php

namespace App\Services;

use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use Illuminate\Support\Carbon;
use RuntimeException;

class AiRecordSummaryService
{
    public function __construct(
        private readonly GeminiTextService $text,
        private readonly VaccineScheduleVersionResolver $versions,
        private readonly ImmunizationSuggestionService $suggestions,
    ) {}

    public function childRecord(ChildProfile $child): string
    {
        $child->loadMissing('vaccinations');
        $records = $child->vaccinations;
        $usableRecords = $records->where('verification_status', '!=', 'rejected');
        $counts = array_fill_keys(['recorded', 'pending_verification', 'upcoming', 'due', 'delayed', 'overdue'], 0);

        foreach ($this->versions->scheduleRowsForChild($child) as $doses) {
            foreach ($doses as $dose) {
                $record = $usableRecords->first(fn (VaccinationRecord $record): bool => $record->vaccine_type_id === $dose->vaccine_type_id
                    && (int) $record->dose_number === (int) $dose->dose_number);
                $status = $record !== null
                    ? ($record->verification_status === 'pending' ? 'pending_verification' : 'recorded')
                    : $this->suggestions->statusForDueDate($dose->dueDateFromBirthdate(Carbon::parse($child->birthdate)));
                $counts[$status]++;
            }
        }

        // Only administrative counts leave the server: no identity, vaccine names,
        // dates, clinical notes, age, address, contacts, or location identifiers.
        return $this->text->generate($this->instructions('one record'), [
            'scope' => 'one_record',
            'record_counts' => [
                'total' => $records->count(),
                'verified' => $records->where('verification_status', 'verified')->count(),
                'pending_verification' => $records->where('verification_status', 'pending')->count(),
                'rejected' => $records->where('verification_status', 'rejected')->count(),
            ],
            'configured_schedule_item_counts' => $counts,
            'next_record_review_status' => $this->suggestions->suggestNextDose($child)['status'],
        ]);
    }

    /** @param iterable<array<string, mixed>> $rows */
    public function followUpBriefing(iterable $rows): string
    {
        $rows = collect($rows);
        if ($rows->isEmpty()) {
            throw new RuntimeException('No children match the current filters. Change the filters before generating a briefing.');
        }

        return $this->text->generate($this->instructions('all matching records, across every results page'), [
            'scope' => 'all_matching_children',
            'matching_children' => $rows->count(),
            'children_by_next_schedule_status' => $rows->countBy('status')->all(),
            'children_by_available_contact_channel' => $rows->countBy('contact_channel')->all(),
            'contact_channels_by_schedule_status' => $rows->groupBy('status')->map(fn ($group): array => $group->countBy('contact_channel')->all())->all(),
            'children_with_pending_verification' => $rows->filter(fn (array $row): bool => $row['child']->vaccinations->contains('verification_status', 'pending'))->count(),
            'pending_verification_records' => $rows->sum(fn (array $row): int => $row['child']->vaccinations->where('verification_status', 'pending')->count()),
        ]);
    }

    private function instructions(string $scope): string
    {
        $format = $scope === 'one record'
            ? 'Use exactly these Markdown headings: ### What this means, ### Why it needs review, ### What to do next. '
                .'Explain this specific record, not the purpose of the assistant. Do not open with This briefing summarizes or describe the audience. '
                .'In What this means, connect the most relevant counts and the next review status in two or three plain-language sentences. '
                .'Distinguish verified entries from schedule coverage: verifying the entries already stored does not establish that all configured schedule items have matching records. '
                .'In Why it needs review, give at most three explanatory bullets. Every bullet must link a supplied finding to its administrative meaning, rather than repeat a label and count. '
                .'If overdue items exist, explain that their configured dates have passed without matching non-rejected records; this may reflect missing documentation and must not be presented as proof of missed vaccinations. '
                .'If pending entries exist, explain why staff verification is still needed. If rejected entries exist, explain they do not count as accepted matching records. '
                .'If all recorded entries are verified, acknowledge that no pending verification is shown, while explaining any outstanding schedule-record discrepancy. '
                .'In What to do next, give at most three ordered action bullets. Each action must say what staff should check AND why, referring to a supplied finding. '
                .'Do not suggest reviewing pending or rejected submissions when their counts are zero. Do not suggest missing contact details: contact availability is not provided. '
                .'When no issue is shown, say there is no outstanding discrepancy in the supplied counts and avoid inventing review tasks. '
                .'Do not make a list of every input number. Do not assert any cause of missing documentation, such as vaccination elsewhere, as fact. '
                .'Use everyday wording such as past the schedule date with no matching record and check the vaccination card. Avoid jargon such as non-rejected records, reconciliation, and schedule coverage. '
                .'Upcoming items are future checklist dates, not proof of clinical eligibility or medical requirements. Prioritize outstanding past-due records over future items. '
            : 'Use exactly these Markdown headings: ### Clinic snapshot, ### What needs attention, ### Suggested work plan. '
                .'Generate an explanatory briefing, not a statistics list. Do not describe the assistant or its audience. '
                .'In Clinic snapshot, write two or three sentences connecting the main schedule-status pattern, pending verification, and available follow-up channels in the selected group. '
                .'In What needs attention, give at most three bullets linking a supplied finding to why it matters for administrative work. Explain past-due statuses as outstanding matching records to check, never as confirmed missed vaccinations. '
                .'Distinguish child counts from submission counts. Pending submissions may belong to children in any status group: do not claim they explain overdue flags or change those flags. '
                .'Use contact_channels_by_schedule_status only to describe which channels are recorded for each status group. A recorded channel does not prove the contact details are current or that the guardian can be reached. '
                .'In Suggested work plan, give at most three numbered steps, ordered by administrative priority. Each step must tie an action to a supplied finding and explain why it comes next. '
                .'For overdue or delayed groups, suggest checking their cards or outside records for missing documentation. For nonzero pending submissions, suggest reviewing those submissions. For nonzero missing-contact counts, suggest updating contacts. '
                .'If channels are recorded for outstanding groups, suggest preparing a staff-reviewed follow-up list after checking records. Do not send reminders or claim any action has been performed. '
                .'Do not recommend catch-up review unless the supplied catch_up_review count is positive. Do not invent rejected submissions or missing contacts. Do not propose pending-verification work when pending counts are zero. '
                .'If no outstanding issues appear, say so and suggest routine record monitoring rather than inventing urgent work. '
                .'Do not list all the numbers again or add hypothetical tasks. Use everyday English: check the vaccination card instead of reconcile history. '
                .'Refer to the selected group, not the whole clinic or barangay, because filters may limit the records. ';

        return 'Write an administrative CHILD immunization record-review briefing. The readers are nurses and adult clinic staff; the records belong to children, not adults. Scope: '.$scope.'. '
            .'Use only the supplied counts and statuses, computed by the application. Do not infer clinical facts, invent numbers, or recalculate schedules. '
            .'Use simple English, at most 250 words. '.$format
            .'Avoid boilerplate and repeating the full input. Never mention your model or provider. '
            .'Due, delayed, and overdue describe configured schedule items without matching non-rejected records, not proof a vaccination was missed. '
            .'Recorded items include unverified submissions; never call pending records confirmed vaccinations. '
            .'Catch-up review is a flag for staff to reconcile history, not a clinical recommendation. Complete means no missing configured routine items, not confirmed clinical completeness. '
            .'Only suggest record reconciliation, reviewing pending submissions, confirming records held elsewhere, checking contact availability, and preparing staff-reviewed follow-up lists. '
            .'Do not identify anyone, provide medical advice, select vaccines or doses, recommend administration dates, prescribe catch-up schedules, or send messages. '
            .'Do not claim contacts were reached or that any action has already happened. Avoid generic disclaimers repeated in the interface.';
    }
}
