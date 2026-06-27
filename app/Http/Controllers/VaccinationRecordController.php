<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Services\ImmunizationSuggestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VaccinationRecordController extends Controller
{
    public function store(Request $request, ChildProfile $child, ImmunizationSuggestionService $suggestions): RedirectResponse
    {
        abort_unless(auth()->user()->isNurse(), 403);
        abort_if($child->barangay_id !== auth()->user()->barangay_id, 403);

        $validated = $request->validate([
            'vaccine_type_id' => ['required', 'exists:vaccine_types,id'],
            'dose_number' => ['nullable', 'integer', 'min:1', 'max:10'],
            'administered_at' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $record = VaccinationRecord::create([
            ...$validated,
            'child_profile_id' => $child->id,
            'recorded_by' => auth()->id(),
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'source' => 'barangay_clinic',
            'verification_status' => 'verified',
        ]);

        $record->update($suggestions->suggestionForRecord($child));

        return to_route('children.show', $child)->with('status', 'Vaccination record saved with next-dose suggestion.');
    }

    public function verify(VaccinationRecord $record): RedirectResponse
    {
        $this->authorizeVerification($record);

        $record->update([
            'verification_status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return to_route('children.show', $record->child_profile_id)->with('status', 'Vaccination record verified.');
    }

    public function reject(VaccinationRecord $record): RedirectResponse
    {
        $this->authorizeVerification($record);

        $record->update([
            'verification_status' => 'rejected',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return to_route('children.show', $record->child_profile_id)->with('status', 'Vaccination record rejected.');
    }

    private function authorizeVerification(VaccinationRecord $record): void
    {
        $record->loadMissing('child');

        abort_unless($record->isPendingVerification(), 403);
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isNurse(), 403);
        abort_if(auth()->user()->isNurse() && $record->child->barangay_id !== auth()->user()->barangay_id, 403);
    }
}
