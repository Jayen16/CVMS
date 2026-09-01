<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use App\Services\InAppNotificationService;
use App\Services\OfflineSyncService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Verification Queue')]
class VerificationQueuePage extends Component
{
    use WithPagination;

    #[Url]
    public ?string $barangay_id = null;

    #[Url]
    public ?string $vaccine_type_id = null;

    #[Url]
    public string $source = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public bool $confirmingAction = false;

    public string $pendingAction = 'verify';

    public ?string $pendingRecordId = null;

    public ?array $pendingRecordSummary = null;

    public function updating($name): void
    {
        if (in_array($name, ['barangay_id', 'vaccine_type_id', 'source', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function promptVerify(string $recordId): void
    {
        $this->openConfirmationModal($recordId, 'verify');
    }

    public function promptReject(string $recordId): void
    {
        $this->openConfirmationModal($recordId, 'reject');
    }

    public function cancelConfirmation(): void
    {
        $this->confirmingAction = false;
        $this->pendingAction = 'verify';
        $this->pendingRecordId = null;
        $this->pendingRecordSummary = null;
    }

    public function confirmPendingAction(): void
    {
        abort_if($this->pendingRecordId === null, 404);

        if ($this->pendingAction === 'verify') {
            $this->verify($this->pendingRecordId);
        } else {
            $this->reject($this->pendingRecordId);
        }
    }

    public function verify(string $recordId, InAppNotificationService $notifications): void
    {
        $record = VaccinationRecord::findOrFail($recordId);
        abort_unless($record->isPendingVerification(), 403);
        abort_unless(auth()->user()->canVerifyVaccinations(), 403);
        abort_if($record->child->barangay_id !== auth()->user()->barangay_id, 403);

        app(OfflineSyncService::class)->queueUpsert(
            tap($record, fn ($model) => $model->update([
                'verification_status' => 'verified',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]))->fresh(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier'])
        );
        $notifications->vaccinationVerified($record);

        $this->cancelConfirmation();
        Flux::toast(variant: 'success', text: 'Vaccination record verified.');
    }

    public function reject(string $recordId, InAppNotificationService $notifications): void
    {
        $record = VaccinationRecord::findOrFail($recordId);
        abort_unless($record->isPendingVerification(), 403);
        abort_unless(auth()->user()->canVerifyVaccinations(), 403);
        abort_if($record->child->barangay_id !== auth()->user()->barangay_id, 403);

        app(OfflineSyncService::class)->queueUpsert(
            tap($record, fn ($model) => $model->update([
                'verification_status' => 'rejected',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]))->fresh(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier'])
        );
        $notifications->vaccinationRejected($record);

        $this->cancelConfirmation();
        Flux::toast(variant: 'success', text: 'Vaccination record rejected.');
    }

    public function render(): View
    {
        abort_unless(auth()->user()->canViewVerificationQueue(), 403);

        $query = VaccinationRecord::query()
            ->with(['child.barangay', 'vaccineType', 'submitter'])
            ->where('verification_status', 'pending');

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereHas('child', fn ($builder) => $builder->where('barangay_id', auth()->user()->barangay_id));
        }

        $query
            ->when($this->barangay_id, fn ($builder) => $builder->whereHas('child', fn ($child) => $child->where('barangay_id', $this->barangay_id)))
            ->when($this->vaccine_type_id, fn ($builder) => $builder->where('vaccine_type_id', $this->vaccine_type_id))
            ->when($this->source !== '', fn ($builder) => $builder->where('source', $this->source))
            ->when($this->from !== '', fn ($builder) => $builder->whereDate('administered_at', '>=', $this->from))
            ->when($this->to !== '', fn ($builder) => $builder->whereDate('administered_at', '<=', $this->to));

        return view('livewire.verification-queue-page', [
            'records' => $query->latest('administered_at')->paginate(15),
            'barangays' => Barangay::orderBy('name')->get(),
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Verification Queue']);
    }

    private function openConfirmationModal(string $recordId, string $action): void
    {
        $record = VaccinationRecord::query()
            ->with(['child.barangay', 'vaccineType', 'submitter'])
            ->findOrFail($recordId);

        abort_unless($record->isPendingVerification(), 403);
        abort_unless(auth()->user()->canVerifyVaccinations(), 403);
        abort_if($record->child->barangay_id !== auth()->user()->barangay_id, 403);

        $this->pendingAction = $action;
        $this->pendingRecordId = $record->id;
        $this->pendingRecordSummary = [
            'child_name' => $record->child->full_name,
            'barangay_name' => $record->child->barangay?->name ?? 'Unassigned',
            'vaccine_name' => $record->vaccineType->name,
            'date_given' => $record->administered_at?->format('M d, Y'),
            'source' => (string) str($record->source)->replace('_', ' ')->title(),
            'submitted_by' => $record->submitter?->name ?? 'N/A',
        ];
        $this->confirmingAction = true;
    }
}
