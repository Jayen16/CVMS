<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
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
    public ?int $barangay_id = null;

    #[Url]
    public ?int $vaccine_type_id = null;

    #[Url]
    public string $source = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function updating($name): void
    {
        if (in_array($name, ['barangay_id', 'vaccine_type_id', 'source', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function verify(int $recordId): void
    {
        $record = VaccinationRecord::findOrFail($recordId);
        abort_unless($record->isPendingVerification(), 403);
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isNurse(), 403);
        abort_if(auth()->user()->isNurse() && $record->child->barangay_id !== auth()->user()->barangay_id, 403);

        app(\App\Services\OfflineSyncService::class)->queueUpsert(
            tap($record, fn ($model) => $model->update([
                'verification_status' => 'verified',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]))->fresh(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier'])
        );

        Flux::toast(variant: 'success', text: 'Vaccination record verified.');
    }

    public function reject(int $recordId): void
    {
        $record = VaccinationRecord::findOrFail($recordId);
        abort_unless($record->isPendingVerification(), 403);
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isNurse(), 403);
        abort_if(auth()->user()->isNurse() && $record->child->barangay_id !== auth()->user()->barangay_id, 403);

        app(\App\Services\OfflineSyncService::class)->queueUpsert(
            tap($record, fn ($model) => $model->update([
                'verification_status' => 'rejected',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]))->fresh(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier'])
        );

        Flux::toast(variant: 'success', text: 'Vaccination record rejected.');
    }

    public function render(): View
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isNurse(), 403);

        $query = VaccinationRecord::query()
            ->with(['child.barangay', 'vaccineType', 'submitter'])
            ->where('verification_status', 'pending');

        if (auth()->user()->isNurse()) {
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
}
