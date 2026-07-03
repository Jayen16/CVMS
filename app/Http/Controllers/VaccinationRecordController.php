<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Services\ImmunizationSuggestionService;
use App\Services\OfflineSyncService;
use App\Services\VaccinationSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VaccinationRecordController extends Controller
{
    public function store(
        Request $request,
        ChildProfile $child,
        ImmunizationSuggestionService $suggestions,
        VaccinationSubmissionService $submissions
    ): RedirectResponse
    {
        $this->authorizeCreate($child);

        $validated = $submissions->validate(auth()->user(), $child, $request->all() + $request->allFiles());
        $record = $submissions->create($child, auth()->user(), $validated);

        $record->update($suggestions->suggestionForRecord($child));

        if (auth()->user()->isParent()) {
            return to_route('children.show', $child)->with('status', 'Vaccination history submitted. It will stay pending until the clinic verifies it.');
        }

        return to_route('children.show', $child)->with('status', 'Vaccination record saved with next-dose suggestion.');
    }

    public function update(
        Request $request,
        VaccinationRecord $record,
        ImmunizationSuggestionService $suggestions,
        VaccinationSubmissionService $submissions
    ): RedirectResponse
    {
        $this->authorizeParentUpdate($record);

        $validated = $submissions->validate(auth()->user(), $record->child, $request->all() + $request->allFiles(), $record);
        $record = $submissions->updatePendingParentRecord($record, $validated);

        $record->update($suggestions->suggestionForRecord($record->child));

        return to_route('children.show', ['child' => $record->child, 'edit_record' => null])
            ->with('status', 'Pending vaccination history updated.');
    }

    public function verify(VaccinationRecord $record, OfflineSyncService $offlineSync): RedirectResponse
    {
        $this->authorizeVerification($record);

        $record->update([
            'verification_status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);
        $offlineSync->queueUpsert($record->fresh(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier']));

        return to_route('children.show', $record->child_profile_id)->with('status', 'Vaccination record verified.');
    }

    public function reject(VaccinationRecord $record, OfflineSyncService $offlineSync): RedirectResponse
    {
        $this->authorizeVerification($record);

        $record->update([
            'verification_status' => 'rejected',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);
        $offlineSync->queueUpsert($record->fresh(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier']));

        return to_route('children.show', $record->child_profile_id)->with('status', 'Vaccination record rejected.');
    }

    public function showProof(VaccinationRecord $record, int $proofIndex): StreamedResponse
    {
        $this->authorizeProofView($record);

        $proofPaths = $record->proofPaths();
        $proofPath = $proofPaths[$proofIndex - 1] ?? null;

        abort_if($proofPath === null, 404);
        abort_unless(Storage::disk('public')->exists($proofPath), 404);

        return Storage::disk('public')->response($proofPath);
    }

    private function authorizeCreate(ChildProfile $child): void
    {
        if (auth()->user()->canManageChildren()) {
            abort_if($child->barangay_id !== auth()->user()->barangay_id, 403);

            return;
        }

        if (auth()->user()->isParent()) {
            abort_unless($child->parents()->whereKey(auth()->id())->exists(), 403);

            return;
        }

        abort(403);
    }

    private function authorizeParentUpdate(VaccinationRecord $record): void
    {
        $record->loadMissing('child');

        abort_unless(auth()->user()->isParent(), 403);
        abort_unless($record->submitted_by === auth()->id(), 403);
        abort_unless($record->isPendingVerification(), 403);
        abort_unless($record->child->parents()->whereKey(auth()->id())->exists(), 403);
    }

    private function authorizeVerification(VaccinationRecord $record): void
    {
        $record->loadMissing('child');

        abort_unless($record->isPendingVerification(), 403);
        abort_unless(auth()->user()->canVerifyVaccinations(), 403);
        abort_if($record->child->barangay_id !== auth()->user()->barangay_id, 403);
    }

    private function authorizeProofView(VaccinationRecord $record): void
    {
        $record->loadMissing('child.parents');

        if (auth()->user()->isSuperAdmin()) {
            return;
        }

        if (auth()->user()->isBarangayAdmin() || auth()->user()->isNurse()) {
            abort_if($record->child->barangay_id !== auth()->user()->barangay_id, 403);

            return;
        }

        if (auth()->user()->isParent()) {
            abort_unless($record->child->parents->contains('id', auth()->id()), 403);

            return;
        }

        abort(403);
    }

}
