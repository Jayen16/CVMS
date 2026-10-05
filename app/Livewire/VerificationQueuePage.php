<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use App\Services\InAppNotificationService;
use App\Services\OfflineSyncService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

#[Title('Verification Queue')]
class VerificationQueuePage extends Component
{
    use WithPagination, WithFileUploads;

    #[Url]
    public ?string $barangay_id = null;

    #[Url]
    public ?string $vaccine_type_id = null;

    #[Url]
    public string $childSearch = '';

    #[Url]
    public string $source = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $view = 'pending';

    public bool $confirmingAction = false;

    public string $pendingAction = 'verify';

    public ?string $pendingRecordId = null;

    public ?array $pendingRecordSummary = null;

    public string $rejectionRemark = '';

    /** @var list<UploadedFile> */
    public array $reviewPhotos = [];

    public function updating($name): void
    {
        if (in_array($name, ['barangay_id', 'vaccine_type_id', 'childSearch', 'source', 'from', 'to', 'view'], true)) {
            $this->resetPage();
        }
    }

    public function setView(string $view): void
    {
        abort_unless(in_array($view, ['pending', 'history'], true), 422);

        $this->view = $view;
        $this->resetPage();
    }

    public function promptVerify(string $recordId): void
    {
        $this->resetValidation();
        $this->rejectionRemark = '';
        $this->reviewPhotos = [];
        $this->openConfirmationModal($recordId, 'verify');
    }

    public function promptReject(string $recordId): void
    {
        $this->resetValidation();
        $this->rejectionRemark = '';
        $this->reviewPhotos = [];
        $this->openConfirmationModal($recordId, 'reject');
    }

    public function cancelConfirmation(): void
    {
        $this->resetValidation();
        $this->confirmingAction = false;
        $this->pendingAction = 'verify';
        $this->pendingRecordId = null;
        $this->pendingRecordSummary = null;
        $this->rejectionRemark = '';
        $this->reviewPhotos = [];
    }

    public function confirmPendingAction(InAppNotificationService $notifications): void
    {
        abort_if($this->pendingRecordId === null, 404);

        if ($this->pendingAction === 'verify') {
            $this->verify($this->pendingRecordId, $notifications);
        } else {
            $this->reject($this->pendingRecordId, $notifications);
        }
    }

    public function verify(string $recordId, InAppNotificationService $notifications): void
    {
        $validated = $this->validateReviewInputs(false);
        $record = VaccinationRecord::findOrFail($recordId);
        abort_unless($record->isPendingVerification(), 403);
        abort_unless(auth()->user()->canVerifyVaccinations(), 403);
        abort_if($record->child->barangay_id !== auth()->user()->barangay_id, 403);

        app(OfflineSyncService::class)->queueUpsert(
            tap($record, function ($model) use ($validated): void {
                $attributes = [
                    'verification_status' => 'verified',
                    'verified_by' => auth()->id(),
                    'verified_at' => now(),
                ];
                if (Schema::hasColumn('vaccination_records', 'nurse_remarks') && filled($validated['rejectionRemark'])) {
                    $attributes['nurse_remarks'] = trim($validated['rejectionRemark']);
                }
                $model->update($attributes);
                $this->attachReviewPhotos($model);
            })->fresh(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier'])
        );
        $notifications->vaccinationVerified($record);

        $this->cancelConfirmation();
        Flux::toast(variant: 'success', text: 'Vaccination record verified.');
    }

    public function reject(string $recordId, InAppNotificationService $notifications): void
    {
        $validated = $this->validateReviewInputs(true);

        $record = VaccinationRecord::findOrFail($recordId);
        abort_unless($record->isPendingVerification(), 403);
        abort_unless(auth()->user()->canVerifyVaccinations(), 403);
        abort_if($record->child->barangay_id !== auth()->user()->barangay_id, 403);

        app(OfflineSyncService::class)->queueUpsert(
            tap($record, function ($model) use ($validated): void {
                $attributes = [
                    'verification_status' => 'rejected',
                    'verified_by' => auth()->id(),
                    'verified_at' => now(),
                ];
                if (Schema::hasColumn('vaccination_records', 'nurse_remarks')) {
                    $attributes['nurse_remarks'] = trim($validated['rejectionRemark']);
                }
                $model->update($attributes);
                $this->attachReviewPhotos($model);
            })->fresh(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier'])
        );
        $notifications->vaccinationRejected($record);

        $this->cancelConfirmation();
        Flux::toast(variant: 'success', text: 'Vaccination record rejected.');
    }

    /** @return array{rejectionRemark: string, reviewPhotos: list<UploadedFile>} */
    private function validateReviewInputs(bool $remarkRequired): array
    {
        return $this->validate([
            'rejectionRemark' => [$remarkRequired ? 'required' : 'nullable', 'string', 'max:1000'],
            'reviewPhotos' => ['nullable', 'array', 'max:5'],
            'reviewPhotos.*' => ['image', 'max:5120'],
        ], [
            'rejectionRemark.required' => 'Please provide a reason for rejecting this vaccination record.',
        ]);
    }

    private function attachReviewPhotos(VaccinationRecord $record): void
    {
        if ($this->reviewPhotos === []) {
            return;
        }

        $paths = $record->proofPaths();
        $uploaders = $record->proofUploaderLabels();
        foreach ($this->reviewPhotos as $photo) {
            $paths[] = $photo->store('vaccination-proofs', config('filesystems.proof_disk', 'local'));
            $uploaders[] = 'Nurse · '.auth()->user()->name;
        }

        $attributes = [
            'proof_path' => $paths[0] ?? null,
            'proof_paths' => array_values(array_unique($paths)),
        ];

        if (Schema::hasColumn('vaccination_records', 'proof_uploaders')) {
            $attributes['proof_uploaders'] = array_values($uploaders);
        }

        $record->update($attributes);
    }

    public function render(): View
    {
        abort_unless(auth()->user()->canViewVerificationQueue(), 403);

        $query = VaccinationRecord::query()
            ->with(['child.barangay', 'vaccineType', 'submitter', 'verifier'])
            ->when(
                $this->view === 'history',
                fn ($builder) => $builder->whereIn('verification_status', ['verified', 'rejected']),
                fn ($builder) => $builder->where('verification_status', 'pending')
            );

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereHas('child', fn ($builder) => $builder->where('barangay_id', auth()->user()->barangay_id));
        }

        $query
            ->when(trim($this->childSearch) !== '', function ($builder): void {
                $search = '%'.trim($this->childSearch).'%';
                $builder->whereHas('child', function ($child) use ($search): void {
                    $child
                        ->whereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?", [$search])
                        ->orWhere('first_name', 'like', $search)
                        ->orWhere('middle_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search);
                });
            })
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
