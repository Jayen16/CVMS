<?php

namespace App\Services;

use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VaccinationSubmissionService
{
    public function __construct(private readonly OfflineSyncService $offlineSync) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(User $user, ChildProfile $child, array $input, ?VaccinationRecord $record = null): array
    {
        if (isset($input['proof_file']) && ! isset($input['proof_files'])) {
            $input['proof_files'] = [$input['proof_file']];
        }

        $rules = [
            'vaccine_type_id' => ['required', 'exists:vaccine_types,id'],
            'dose_number' => [
                'nullable',
                'integer',
                'min:1',
                'max:10',
                Rule::unique('vaccination_records', 'dose_number')
                    ->ignore($record?->id)
                    ->where(fn ($query) => $query
                        ->where('child_profile_id', $child->id)
                        ->where('vaccine_type_id', (string) ($input['vaccine_type_id'] ?? ''))
                        ->where('verification_status', '!=', 'rejected')),
            ],
            'administered_at' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'client_submission_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('vaccination_records', 'client_submission_id')->ignore($record?->id),
            ],
        ];

        if ($user->isParent()) {
            $rules['clinic_name'] = ['required', 'string', 'max:255'];
            $rules['clinic_location'] = ['nullable', 'string', 'max:255'];
            $rules['proof_files'] = ['nullable', 'array', 'max:5'];
            $rules['proof_files.*'] = ['image', 'max:5120'];
        } else {
            $rules['vaccine_inventory_item_id'] = [
                'nullable',
                'uuid',
                'exists:vaccine_inventory_items,id',
            ];
        }

        return Validator::make($input, $rules, [
            'dose_number.unique' => 'This dose number has already been recorded for this vaccine and child.',
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(ChildProfile $child, User $user, array $validated): VaccinationRecord
    {
        $proofPaths = $user->isParent() ? $this->storeProofs($validated['proof_files'] ?? []) : [];

        $record = VaccinationRecord::create([
            ...$validated,
            'child_profile_id' => $child->id,
            'recorded_by' => $user->id,
            'submitted_by' => $user->isParent() ? $user->id : null,
            'verified_by' => $user->isNurse() ? $user->id : null,
            'verified_at' => $user->isNurse() ? now() : null,
            'source' => $user->isParent() ? 'outside_clinic' : 'barangay_clinic',
            'verification_status' => $user->isParent() ? 'pending' : 'verified',
            'proof_path' => $proofPaths[0] ?? null,
            'proof_paths' => $proofPaths === [] ? null : $proofPaths,
        ]);

        $record->load(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier']);
        $this->offlineSync->queueUpsert($record);

        if ($user->isParent()) {
            app(InAppNotificationService::class)->vaccinationSubmitted($record);
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updatePendingParentRecord(VaccinationRecord $record, array $validated): VaccinationRecord
    {
        $proofPaths = $record->proofPaths();

        if (array_key_exists('proof_files', $validated)) {
            $newProofPaths = $this->storeProofs($validated['proof_files'] ?? []);

            if ($newProofPaths !== []) {
                $this->deleteProofs($proofPaths);
                $proofPaths = $newProofPaths;
            }
        }

        $record->update([
            ...$validated,
            'proof_path' => $proofPaths[0] ?? null,
            'proof_paths' => $proofPaths === [] ? null : $proofPaths,
            'verified_by' => null,
            'verified_at' => null,
            'verification_status' => 'pending',
        ]);

        $fresh = $record->fresh(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier']);
        $this->offlineSync->queueUpsert($fresh);

        return $fresh;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<VaccinationRecord>
     */
    public function syncOfflineBatch(User $user, ChildProfile $child, array $records): array
    {
        $saved = [];

        foreach ($records as $input) {
            $existing = filled($input['client_submission_id'] ?? null)
                ? VaccinationRecord::where('client_submission_id', $input['client_submission_id'])->first()
                : null;

            if ($existing) {
                $saved[] = $existing;

                continue;
            }

            $validated = $this->validate($user, $child, $input);
            $saved[] = $this->create($child, $user, $validated);
        }

        return $saved;
    }

    /**
     * @return list<string>
     */
    private function storeProofs(mixed $proofFiles): array
    {
        if (! is_array($proofFiles)) {
            return [];
        }

        $stored = [];

        foreach ($proofFiles as $proofFile) {
            if (! $proofFile instanceof UploadedFile) {
                continue;
            }

            $stored[] = $proofFile->store('vaccination-proofs', 'public');
        }

        return $stored;
    }

    /**
     * @param  list<string>  $proofPaths
     */
    private function deleteProofs(array $proofPaths): void
    {
        if ($proofPaths === []) {
            return;
        }

        Storage::disk('public')->delete($proofPaths);
    }
}
