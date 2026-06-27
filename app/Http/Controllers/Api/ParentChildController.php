<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use App\Services\ImmunizationSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ParentChildController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorizeParent();

        $children = auth()->user()
            ->linkedChildren()
            ->with(['barangay'])
            ->orderBy('last_name')
            ->get()
            ->map(fn (ChildProfile $child) => $this->childPayload($child));

        return response()->json([
            'data' => $children,
        ]);
    }

    public function vaccinations(ChildProfile $child, ImmunizationSuggestionService $suggestions): JsonResponse
    {
        $this->authorizeLinkedChild($child);

        $child->load(['barangay', 'vaccinations.vaccineType', 'vaccinations.recorder', 'vaccinations.submitter', 'vaccinations.verifier']);

        return response()->json([
            'data' => [
                'child' => $this->childPayload($child),
                'suggestion' => $this->suggestionPayload($suggestions->suggestNextDose($child)),
                'vaccinations' => $child->vaccinations
                    ->sortByDesc('administered_at')
                    ->values()
                    ->map(fn (VaccinationRecord $record) => $this->recordPayload($record)),
            ],
        ]);
    }

    public function storeVaccination(Request $request, ChildProfile $child, ImmunizationSuggestionService $suggestions): JsonResponse
    {
        $this->authorizeLinkedChild($child);

        $validated = $request->validate([
            'vaccine_type_id' => ['required', 'exists:vaccine_types,id'],
            'dose_number' => ['nullable', 'integer', 'min:1', 'max:10'],
            'administered_at' => ['required', 'date', 'before_or_equal:today'],
            'clinic_name' => ['required', 'string', 'max:255'],
            'clinic_location' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $record = VaccinationRecord::create([
            ...$validated,
            'child_profile_id' => $child->id,
            'recorded_by' => auth()->id(),
            'submitted_by' => auth()->id(),
            'source' => 'outside_clinic',
            'verification_status' => 'pending',
        ]);

        $record->update($suggestions->suggestionForRecord($child));
        $record->load(['vaccineType', 'recorder', 'submitter', 'verifier']);

        return response()->json([
            'message' => 'Vaccination submitted for nurse verification.',
            'data' => $this->recordPayload($record),
        ], 201);
    }

    public function vaccines(): JsonResponse
    {
        $this->authorizeParent();

        return response()->json([
            'data' => VaccineType::where('active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }

    private function authorizeParent(): void
    {
        abort_unless(auth()->user()->isParent(), 403);
    }

    private function authorizeLinkedChild(ChildProfile $child): void
    {
        $this->authorizeParent();

        abort_unless(
            auth()->user()->linkedChildren()->whereKey($child->id)->exists(),
            403
        );
    }

    /**
     * @return array{id: int, name: string, birthdate: string|null, age: string, sex: string, barangay: string|null}
     */
    private function childPayload(ChildProfile $child): array
    {
        return [
            'id' => $child->id,
            'name' => $child->full_name,
            'birthdate' => Carbon::parse($child->birthdate)->toDateString(),
            'age' => $child->ageLabel(),
            'sex' => $child->sex,
            'barangay' => $child->barangay?->name,
        ];
    }

    /**
     * @param  array{vaccine_code: string|null, vaccine_name: string|null, dose_number: int|null, due_at: Carbon|null, action_at: Carbon|null, status: string, due_label: string|null, note: string, checks: list<string>}  $suggestion
     * @return array{vaccine_code: string|null, vaccine_name: string|null, dose_number: int|null, due_at: string|null, action_at: string|null, status: string, due_label: string|null, note: string, checks: list<string>}
     */
    private function suggestionPayload(array $suggestion): array
    {
        return [
            'vaccine_code' => $suggestion['vaccine_code'],
            'vaccine_name' => $suggestion['vaccine_name'],
            'dose_number' => $suggestion['dose_number'],
            'due_at' => $suggestion['due_at']?->toDateString(),
            'action_at' => $suggestion['action_at']?->toDateString(),
            'status' => $suggestion['status'],
            'due_label' => $suggestion['due_label'],
            'note' => $suggestion['note'],
            'checks' => $suggestion['checks'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recordPayload(VaccinationRecord $record): array
    {
        return [
            'id' => $record->id,
            'vaccine' => [
                'id' => $record->vaccineType->id,
                'code' => $record->vaccineType->code,
                'name' => $record->vaccineType->name,
            ],
            'dose_number' => $record->dose_number,
            'administered_at' => Carbon::parse($record->administered_at)->toDateString(),
            'source' => $record->source,
            'verification_status' => $record->verification_status,
            'clinic_name' => $record->clinic_name,
            'clinic_location' => $record->clinic_location,
            'remarks' => $record->remarks,
            'submitted_by' => $record->submitter?->name,
            'verified_by' => $record->verifier?->name,
            'verified_at' => $record->verified_at ? Carbon::parse($record->verified_at)->toIso8601String() : null,
            'next_due_at' => $record->next_due_at ? Carbon::parse($record->next_due_at)->toDateString() : null,
            'suggested_vaccine' => $record->suggested_vaccine,
        ];
    }
}
