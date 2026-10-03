<?php

namespace App\Livewire;

use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Services\ImmunizationSuggestionService;
use App\Services\VaccineScheduleVersionResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

class ChildrenScheduleCalendar extends Component
{
    #[Url]
    public string $calendarMonth = '';

    #[Url]
    public ?string $selectedScheduleDate = null;

    public function mount(): void
    {
        $this->calendarMonth = $this->calendarMonth !== ''
            ? $this->calendarMonth
            : Carbon::today()->format('Y-m');
    }

    public function render(
        VaccineScheduleVersionResolver $scheduleVersions,
        ImmunizationSuggestionService $suggestions,
    ): View {
        abort_unless(auth()->user()->isParent(), 403);

        $scheduleMonth = Carbon::createFromFormat('!Y-m', $this->calendarMonth) ?: Carbon::today()->startOfMonth();
        $children = ChildProfile::query()
            ->visibleTo(auth()->user())
            ->with(['barangay', 'vaccinations.vaccineType', 'seriesVersions.scheduleVersion'])
            ->get();
        $scheduleItems = $children
            ->flatMap(fn (ChildProfile $child): array => $this->scheduleItemsForChild($child, $scheduleVersions, $suggestions))
            ->sortBy('date')
            ->values();
        $selectedScheduleDateValue = null;

        if ($this->selectedScheduleDate !== null) {
            try {
                $parsedDate = Carbon::createFromFormat('!Y-m-d', $this->selectedScheduleDate);
                $selectedScheduleDateValue = $parsedDate instanceof Carbon ? $parsedDate : null;
            } catch (\Throwable) {
                $this->selectedScheduleDate = null;
            }
        }

        $upcomingScheduleItems = $scheduleItems
            ->filter(fn (array $item): bool => $item['date']->greaterThanOrEqualTo(today()->subMonths(2)))
            ->take(8)
            ->values();
        $selectedScheduleItems = $selectedScheduleDateValue === null
            ? collect()
            : $scheduleItems
                ->filter(fn (array $item): bool => $item['date']->isSameDay($selectedScheduleDateValue))
                ->values();

        return view('livewire.children-schedule-calendar', [
            'scheduleMonth' => $scheduleMonth,
            'scheduleItems' => $scheduleItems,
            'upcomingScheduleItems' => $upcomingScheduleItems,
            'selectedScheduleItems' => $selectedScheduleItems,
            'selectedScheduleDateValue' => $selectedScheduleDateValue,
        ])->layout('layouts.app', [
            'title' => 'Family schedule',
        ]);
    }

    public function updatedCalendarMonth(): void
    {
        $this->selectedScheduleDate = null;
    }

    public function selectScheduleDate(string $date): void
    {
        try {
            $selectedDate = Carbon::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return;
        }

        if (! $selectedDate instanceof Carbon || $selectedDate->format('Y-m-d') !== $date) {
            return;
        }

        $this->selectedScheduleDate = $date;
    }

    public function clearScheduleDate(): void
    {
        $this->selectedScheduleDate = null;
    }

    /**
     * @return list<array{date: Carbon, child: string, child_id: int, photo_path: ?string, vaccine: string, dose: int, status: string}>
     */
    private function scheduleItemsForChild(
        ChildProfile $child,
        VaccineScheduleVersionResolver $versions,
        ImmunizationSuggestionService $suggestions,
    ): array {
        $rows = $versions->scheduleRowsForChild($child);
        $items = [];

        foreach ($rows as $doses) {
            $vaccine = $doses->first()?->vaccineType;

            if ($vaccine === null) {
                continue;
            }

            $records = $child->vaccinations
                ->filter(fn (VaccinationRecord $record): bool => $record->vaccine_type_id === $vaccine->id && $record->verification_status !== 'rejected')
                ->sortBy('administered_at')
                ->values();

            foreach ($doses as $dose) {
                $record = $records->first(fn (VaccinationRecord $entry): bool => (int) $entry->dose_number === (int) $dose->dose_number);
                $dueDate = $dose->dueDateFromBirthdate(Carbon::parse($child->birthdate)->startOfDay());

                if ($record !== null) {
                    $eventDate = Carbon::parse($record->administered_at)->startOfDay();
                    $status = $record->verification_status === 'pending' ? 'pending' : 'completed';
                } else {
                    $eventDate = $dueDate->copy()->startOfDay();
                    $status = $suggestions->statusForDueDate($dueDate);
                }

                $items[] = [
                    'date' => $eventDate,
                    'child' => $child->full_name,
                    'child_id' => $child->id,
                    'photo_path' => $child->photo_path,
                    'vaccine' => $vaccine->name,
                    'dose' => (int) $dose->dose_number,
                    'status' => $status,
                ];
            }
        }

        return $items;
    }
}
