<?php

namespace App\Livewire;

use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Models\VaccineInventoryItem;
use App\Models\VaccineType;
use App\Services\ImmunizationSuggestionService;
use App\Services\VaccineScheduleVersionResolver;
use App\Services\VaccinationSubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ChildShowPage extends Component
{
    use WithFileUploads, WithPagination;

    public ChildProfile $child;

    public ?string $submissionVaccineTypeId = null;

    public $submissionDoseNumber = null;

    public ?string $submissionAdministeredAt = null;

    public ?string $submissionClinicName = null;

    public ?string $submissionClinicLocation = null;

    public ?string $submissionRemarks = null;

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $submissionProofFiles = [];

    public ?string $editingRecordId = null;

    public string $childTab = 'schedule';

    public bool $submitHistoryOpen = false;

    public int $submissionStep = 1;

    public bool $submissionSubmitted = false;

    #[Url]
    public int $perPage = 10;

    #[Url]
    public string $calendarMonth = '';

    #[Url]
    public ?string $selectedScheduleDate = null;

    #[Url]
    public int $upcomingPage = 1;

    #[Url]
    public int $overduePage = 1;

    public function mount(ChildProfile $child): void
    {
        $this->child = $child;
        $this->child->loadMissing('barangay');
        $this->calendarMonth = $this->calendarMonth !== '' ? $this->calendarMonth : Carbon::today()->format('Y-m');
        $this->childTab = in_array(request()->string('tab')->toString(), ['vaccination', 'parents'], true)
            ? request()->string('tab')->toString()
            : 'schedule';
        $this->submissionClinicName = $this->child->barangay?->name ?? 'Current clinic barangay';
        $this->submissionClinicLocation = 'Indang, Cavite, Barangay 4 (pob.), Indang, Cavite, 4122';
        $this->editingRecordId = request()->string('edit_record')->toString() ?: null;
        $this->submitHistoryOpen = $this->editingRecordId !== null;
        $this->submissionStep = $this->editingRecordId !== null ? 2 : 1;

        if ($this->editingRecordId !== null && auth()->user()->isParent()) {
            $record = VaccinationRecord::query()
                ->where('child_profile_id', $child->id)
                ->whereKey($this->editingRecordId)
                ->first();

            if ($record) {
                $this->submissionVaccineTypeId = $record->vaccine_type_id;
                $this->submissionDoseNumber = $record->dose_number;
                $this->submissionAdministeredAt = $record->administered_at?->toDateString();
                $this->submissionClinicName = $record->clinic_name;
                $this->submissionClinicLocation = $record->clinic_location;
                $this->submissionRemarks = $record->remarks;
            }
        }
    }

    public function submitVaccinationHistory(VaccinationSubmissionService $submissions, ImmunizationSuggestionService $suggestions): void
    {
        abort_unless(auth()->user()->isParent(), 403);
        abort_unless($this->child->parents()->whereKey(auth()->id())->exists(), 403);
        $this->childTab = 'vaccination';

        $record = $this->editingRecordId
            ? VaccinationRecord::query()->where('child_profile_id', $this->child->id)->findOrFail($this->editingRecordId)
            : null;

        if ($record) {
            abort_unless($record->submitted_by === auth()->id() && $record->isParentEditable(), 403);
        }

        $input = [
            'vaccine_type_id' => $this->submissionVaccineTypeId,
            'dose_number' => $this->submissionDoseNumber,
            'administered_at' => $this->submissionAdministeredAt,
            'clinic_name' => $this->submissionClinicName,
            'clinic_location' => $this->submissionClinicLocation,
            'remarks' => $this->submissionRemarks,
            'proof_files' => $this->submissionProofFiles,
        ];

        try {
            $validated = $submissions->validate(auth()->user(), $this->child, $input, $record);
        } catch (ValidationException $exception) {
            $this->submitHistoryOpen = true;
            $this->submissionStep = isset($exception->errors()['proof_files']) || isset($exception->errors()['proof_files.*']) ? 2 : 1;
            $this->dispatch(
                'vaccination-validation-failed',
                step: $this->submissionStep,
            );

            throw $exception;
        }

        if ($record) {
            $record = $submissions->updatePendingParentRecord($record, $validated);
            $message = 'Pending vaccination history updated.';
        } else {
            $record = $submissions->create($this->child, auth()->user(), $validated);
            $message = 'Vaccination history submitted. It will stay pending until the clinic verifies it.';
        }

        $record->update($suggestions->suggestionForRecord($this->child));
        $this->submitHistoryOpen = true;
        $this->submissionStep = 4;
        $this->submissionSubmitted = true;
        $this->resetSubmissionForm();
        $this->dispatch('vaccination-submitted', message: $message);
    }

    private function resetSubmissionForm(): void
    {
        $this->submissionVaccineTypeId = null;
        $this->submissionDoseNumber = null;
        $this->submissionAdministeredAt = null;
        $this->submissionClinicName = $this->child->barangay?->name ?? 'Current clinic barangay';
        $this->submissionClinicLocation = 'Indang, Cavite, Barangay 4 (pob.), Indang, Cavite, 4122';
        $this->submissionRemarks = null;
        $this->submissionProofFiles = [];
        $this->editingRecordId = null;
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

        $upcomingAllItems = collect($scheduleItems)
            ->filter(fn (array $item): bool => $item['date']->greaterThanOrEqualTo(today()->subMonths(2)))
            ->values();
        $upcomingPages = max(1, (int) ceil($upcomingAllItems->count() / 4));
        $this->upcomingPage = max(1, min($this->upcomingPage, $upcomingPages));
        $upcomingScheduleItems = $upcomingAllItems->forPage($this->upcomingPage, 4)->values();

        $overdueAllItems = collect($scheduleItems)
            ->filter(fn (array $item): bool => $item['status'] === 'overdue')
            ->values();
        $overduePages = max(1, (int) ceil($overdueAllItems->count() / 4));
        $this->overduePage = max(1, min($this->overduePage, $overduePages));
        $overdueScheduleItems = $overdueAllItems->forPage($this->overduePage, 4)->values();
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
                ->latest('administered_at')
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
            'upcomingPage' => $this->upcomingPage,
            'upcomingPages' => $upcomingPages,
            'overdueScheduleItems' => $overdueScheduleItems,
            'overduePage' => $this->overduePage,
            'overduePages' => $overduePages,
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
        $this->upcomingPage = 1;
        $this->overduePage = 1;
    }

    public function previousUpcomingPage(): void
    {
        $this->upcomingPage = max(1, $this->upcomingPage - 1);
    }

    public function nextUpcomingPage(): void
    {
        $this->upcomingPage++;
    }

    public function previousOverduePage(): void
    {
        $this->overduePage = max(1, $this->overduePage - 1);
    }

    public function nextOverduePage(): void
    {
        $this->overduePage++;
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
