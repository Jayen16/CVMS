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
    public function __construct(private readonly OfflineSyncService $offlineSync)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(User $user, ChildProfile $child, array $input, ?VaccinationRecord $record = null): array
    {
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
                        ->where('vaccine_type_id', (int) ($input['vaccine_type_id'] ?? 0))
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
            $rules['proof_file'] = ['nullable', 'file', 'image', 'max:5120'];
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
        $proofPath = $user->isParent() ? $this->storeProof($validated['proof_file'] ?? null) : null;

        $record = VaccinationRecord::create([
            ...$validated,
            'child_profile_id' => $child->id,
            'recorded_by' => $user->id,
            'submitted_by' => $user->isParent() ? $user->id : null,
            'verified_by' => $user->isNurse() ? $user->id : null,
            'verified_at' => $user->isNurse() ? now() : null,
            'source' => $user->isParent() ? 'outside_clinic' : 'barangay_clinic',
            'verification_status' => $user->isParent() ? 'pending' : 'verified',
            'proof_path' => $proofPath,
        ]);

        $this->offlineSync->queueUpsert($record->load(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier']));

        return $record;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updatePendingParentRecord(VaccinationRecord $record, array $validated): VaccinationRecord
    {
        $proofPath = $record->proof_path;

        if (array_key_exists('proof_file', $validated)) {
            $newProofPath = $this->storeProof($validated['proof_file']);

            if ($newProofPath !== null) {
                if ($proofPath !== null) {
                    Storage::disk('public')->delete($proofPath);
                }

                $proofPath = $newProofPath;
            }
        }

        $record->update([
            ...$validated,
            'proof_path' => $proofPath,
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

    private function storeProof(mixed $proofFile): ?string
    {
        if (! $proofFile instanceof UploadedFile) {
            return null;
        }

        return $proofFile->store('vaccination-proofs', 'public');
    }
}
