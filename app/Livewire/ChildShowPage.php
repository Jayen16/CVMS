<?php

namespace App\Livewire;

use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Models\VaccineInventoryItem;
use App\Models\VaccineType;
use App\Services\ImmunizationSuggestionService;
use App\Services\VaccineScheduleVersionResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ChildShowPage extends Component
{
    use WithPagination;

    public ChildProfile $child;

    #[Url]
    public int $perPage = 10;

    #[Url]
    public string $calendarMonth = '';

    #[Url]
    public ?string $selectedScheduleDate = null;

    public function mount(ChildProfile $child): void
    {
        $this->child = $child;
        $this->calendarMonth = $this->calendarMonth !== '' ? $this->calendarMonth : Carbon::today()->format('Y-m');
    }

    public function render(ImmunizationSuggestionService $suggestions, VaccineScheduleVersionResolver $scheduleVersions): View
    {
        abort_unless(auth()->user()->canViewChildrenRegistry(), 403);
        $this->authorizeChild($this->child);

        $this->child->load([
            'barangay',
            'parents',
            'vaccinations.vaccineType',
            'vaccinations.recorder',
            'vaccinations.submitter',
            'vaccinations.verifier',
            'adverseEventReports.vaccineType',
            'adverseEventReports.vaccinationRecord.vaccineType',
            'adverseEventReports.reporter',
        ]);

        $editableRecord = null;

        $this->perPage = in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 10;

        if (auth()->user()->isParent() && request()->filled('edit_record')) {
            $editableRecord = $this->child->vaccinations
                ->first(fn (VaccinationRecord $record) => $record->id === request()->string('edit_record')->toString());

            abort_if($editableRecord === null, 404);
            abort_if($editableRecord->submitted_by !== auth()->id(), 403);
            abort_if(! $editableRecord->isParentEditable(), 403);
        }

        $scheduleItems = $this->scheduleCalendarItems($this->child, $scheduleVersions, $suggestions);
        $scheduleMonth = Carbon::createFromFormat('!Y-m', $this->calendarMonth) ?: Carbon::today()->startOfMonth();
        $selectedScheduleDate = null;

        if ($this->selectedScheduleDate !== null) {
            try {
                $parsedScheduleDate = Carbon::createFromFormat('!Y-m-d', $this->selectedScheduleDate);
                $selectedScheduleDate = $parsedScheduleDate instanceof Carbon ? $parsedScheduleDate : null;
            } catch (\Throwable) {
                $this->selectedScheduleDate = null;
            }
        }

        $upcomingScheduleItems = collect($scheduleItems)
            ->filter(fn (array $item): bool => $item['date']->greaterThanOrEqualTo(today()->subMonths(2)))
            ->take(8)
            ->values();
        $overdueScheduleItems = collect($scheduleItems)
            ->filter(fn (array $item): bool => $item['status'] === 'overdue')
            ->values();
        $selectedScheduleItems = collect();

        if ($selectedScheduleDate !== null) {
            $selectedScheduleItems = collect($scheduleItems)
                ->filter(fn (array $item): bool => $item['date']->isSameDay($selectedScheduleDate))
                ->values();
        }

        return view('children.show', [
            'child' => $this->child,
            'vaccinations' => VaccinationRecord::query()
                ->with(['vaccineType', 'recorder', 'submitter', 'verifier'])
                ->where('child_profile_id', $this->child->id)
                ->latest('created_at')
                ->paginate($this->perPage),
            'suggestion' => $suggestions->suggestNextDose($this->child),
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'inventoryItems' => VaccineInventoryItem::query()
                ->where('barangay_id', $this->child->barangay_id)
                ->with('vaccineType')
                ->withSum(['transactions as stock_in' => fn ($query) => $query->where('movement', 'in')], 'quantity')
                ->withSum(['transactions as stock_out' => fn ($query) => $query->where('movement', 'out')], 'quantity')
                ->orderBy('item_code')
                ->get()
                ->filter(fn (VaccineInventoryItem $item): bool => $item->availableStock() > 0),
            'editableRecord' => $editableRecord,
            'scheduleMonth' => $scheduleMonth,
            'scheduleItems' => $scheduleItems,
            'upcomingScheduleItems' => $upcomingScheduleItems,
            'overdueScheduleItems' => $overdueScheduleItems,
            'selectedScheduleItems' => $selectedScheduleItems,
            'selectedScheduleDateValue' => $selectedScheduleDate,
        ])->layout('layouts.app', [
            'title' => $this->child->full_name,
        ]);
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 10;
        $this->resetPage();
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
     * @return list<string>
     */
    public function proofImageUrls(VaccinationRecord $record): array
    {
        return collect($record->proofPaths())
            ->keys()
            ->map(fn (int $index): string => route('vaccinations.proofs.show', [
                'record' => $record,
                'proofIndex' => $index + 1,
            ]))
            ->values()
            ->all();
    }

    /**
     * @return list<array{date: Carbon, vaccine: string, dose: int, status: string, label: string, location: string}>
     */
    private function scheduleCalendarItems(ChildProfile $child, VaccineScheduleVersionResolver $versions, ImmunizationSuggestionService $suggestions): array
    {
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
                    'vaccine' => $vaccine->name,
                    'dose' => (int) $dose->dose_number,
                    'status' => $status,
                    'label' => $dose->label,
                    'location' => $child->barangay?->name ?? 'Health center',
                ];
            }
        }

        return collect($items)->sortBy('date')->values()->all();
    }

    private function authorizeChild(ChildProfile $child): void
    {
        abort_if(auth()->user()->isMunicipalAdmin() && ! auth()->user()->canAccessBarangay($child->barangay_id), 403);
        abort_if(auth()->user()->isNurse() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(auth()->user()->isBarangayAdmin() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(auth()->user()->isParent() && ! $child->parents()->whereKey(auth()->id())->exists(), 403);
    }
}
