<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use App\Services\ImmunizationSuggestionService;
use App\Services\OfflineSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChildProfileController extends Controller
{
    private const CHILD_PROFILE_RULES = [
        'first_name' => ['required', 'string', 'max:255'],
        'middle_name' => ['nullable', 'string', 'max:255'],
        'last_name' => ['required', 'string', 'max:255'],
        'birthdate' => ['required', 'date', 'before_or_equal:today'],
        'sex' => ['required', 'in:female,male'],
        'guardian_name' => ['required', 'string', 'max:255'],
        'guardian_contact' => ['nullable', 'string', 'max:255'],
        'address' => ['nullable', 'string', 'max:1000'],
    ];

    public function index(Request $request): View
    {
        abort_unless(auth()->user()->canViewChildrenRegistry(), 403);

        $vaccineTypeId = $request->string('vaccine_type_id')->toString() ?: null;

        return view('children.index', [
            'children' => $this->visibleChildren()
                ->with(['barangay', 'vaccinations'])
                ->when($vaccineTypeId && ! auth()->user()->isParent(), fn (Builder $query) => $query->whereHas('vaccinations', fn (Builder $records) => $records->where('vaccine_type_id', $vaccineTypeId)->where('verification_status', 'verified')))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'selectedVaccineTypeId' => $vaccineTypeId,
        ]);
    }

    public function archiveIndex(): View
    {
        abort_unless(auth()->user()->canArchiveChildren(), 403);

        $children = ChildProfile::withoutGlobalScope('not_archived')
            ->whereNotNull('archived_at')
            ->whereIn('barangay_id', auth()->user()->accessibleBarangayIds())
            ->with(['barangay', 'archiver'])
            ->latest('archived_at')
            ->paginate(12);

        return view('children.archive', ['children' => $children]);
    }

    public function archive(Request $request, string $childId, OfflineSyncService $offlineSync): RedirectResponse
    {
        abort_unless(auth()->user()->canArchiveChildren(), 403);

        $child = ChildProfile::withoutGlobalScope('not_archived')->findOrFail($childId);
        $this->authorizeChildArchive($child);

        $validated = $request->validate([
            'archive_reason' => ['required', 'string', 'max:100'],
        ]);

        abort_if($child->isArchived(), 422, 'This child record is already archived.');

        $child->update([
            'archived_at' => now(),
            'archived_by' => auth()->id(),
            'archive_reason' => $validated['archive_reason'],
        ]);
        $offlineSync->queueDelete($child->fresh());

        AuditLog::recordAction('child_archived', 'Archived child record '.$child->full_name, $child, [
            'reason' => $validated['archive_reason'],
        ]);

        return to_route('children.index')->with('status', 'Child record archived. Clinical history was retained.');
    }

    public function restore(string $childId, OfflineSyncService $offlineSync): RedirectResponse
    {
        abort_unless(auth()->user()->canArchiveChildren(), 403);

        $child = ChildProfile::withoutGlobalScope('not_archived')->findOrFail($childId);
        $this->authorizeChildArchive($child);
        abort_unless($child->isArchived(), 422, 'This child record is not archived.');

        $child->update([
            'archived_at' => null,
            'archived_by' => null,
            'archive_reason' => null,
        ]);
        $offlineSync->queueUpsert($child->fresh()->load(['barangay', 'creator']));

        AuditLog::recordAction('child_restored', 'Restored child record '.$child->full_name, $child);

        return to_route('children.archive.index')->with('status', 'Child record restored to the active registry.');
    }

    public function create(): View
    {
        abort_unless(auth()->user()->canManageChildren(), 403);
        abort_if(auth()->user()->barangay_id === null, 403, 'A nurse must be assigned to a barangay before creating child profiles.');

        return view('children.create', [
            'child' => new ChildProfile,
            'barangays' => Barangay::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, OfflineSyncService $offlineSync): RedirectResponse
    {
        abort_unless(auth()->user()->canManageChildren(), 403);
        abort_if(auth()->user()->barangay_id === null, 403);

        $validated = $this->validateChildProfile($request);
        $validated['created_by'] = auth()->id();

        $this->ensureNoDuplicateChild($validated);

        $child = ChildProfile::create($validated);
        $offlineSync->queueUpsert($child->load(['barangay', 'creator']));

        return to_route('children.show', $child)->with('status', 'Child profile created.');
    }

    public function edit(ChildProfile $child): View
    {
        $this->authorizeChildUpdate($child);

        return view('children.edit', [
            'child' => $child,
            'barangays' => Barangay::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ChildProfile $child, OfflineSyncService $offlineSync): RedirectResponse
    {
        $this->authorizeChildUpdate($child);

        $validated = $this->validateChildProfile($request);

        $this->ensureNoDuplicateChild($validated, $child);

        $child->update($validated);
        $offlineSync->queueUpsert($child->fresh()->load(['barangay', 'creator']));

        return to_route('children.show', $child)->with('status', 'Child profile updated.');
    }

    public function transfer(Request $request, ChildProfile $child, OfflineSyncService $offlineSync): RedirectResponse
    {
        $this->authorizeChildTransfer($child);

        $validated = $request->validate([
            'barangay_id' => ['required', 'exists:barangays,id'],
        ]);

        if ($validated['barangay_id'] === $child->barangay_id) {
            return back()->with('status', 'Child is already assigned to that barangay.');
        }

        $this->ensureNoDuplicateChild([
            'barangay_id' => $validated['barangay_id'],
            'birthdate' => $child->birthdate->toDateString(),
            'first_name' => $child->first_name,
            'last_name' => $child->last_name,
        ], $child);

        $child->update([
            'barangay_id' => $validated['barangay_id'],
        ]);

        $offlineSync->queueUpsert($child->fresh()->load(['barangay', 'creator']));

        if (auth()->user()->isBarangayAdmin() && auth()->user()->barangay_id !== $child->barangay_id) {
            return to_route('children.index')->with('status', 'Child transferred to a new barangay.');
        }

        return to_route('children.show', $child)->with('status', 'Child transferred to a new barangay.');
    }

    public function show(ChildProfile $child, ImmunizationSuggestionService $suggestions): View
    {
        abort_unless(auth()->user()->canViewChildrenRegistry(), 403);
        $this->authorizeChild($child);

        $child->load([
            'barangay',
            'parents',
            'vaccinations.vaccineType',
            'vaccinations.recorder',
            'vaccinations.submitter',
            'vaccinations.verifier',
            'adverseEventReports.vaccineType',
            'adverseEventReports.reporter',
        ]);
        $editableRecord = null;

        if (auth()->user()->isParent() && request()->filled('edit_record')) {
            $editableRecord = $child->vaccinations
                ->first(fn (VaccinationRecord $record) => $record->id === request()->string('edit_record')->toString());

            abort_if($editableRecord === null, 404);
            abort_if($editableRecord->submitted_by !== auth()->id(), 403);
            abort_if(! $editableRecord->isPendingVerification(), 403);
        }

        return view('children.show', [
            'child' => $child,
            'suggestion' => $suggestions->suggestNextDose($child),
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'editableRecord' => $editableRecord,
        ]);
    }

    /**
     * @return Builder<ChildProfile>
     */
    private function visibleChildren(): Builder
    {
        $query = ChildProfile::query();

        return $query->visibleTo(auth()->user());
    }

    private function authorizeChild(ChildProfile $child): void
    {
        abort_if(auth()->user()->isNurse() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(auth()->user()->isBarangayAdmin() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(auth()->user()->isMunicipalAdmin() && ! auth()->user()->accessibleBarangayIds()->contains($child->barangay_id), 403);
        abort_if(
            auth()->user()->isParent() && ! $child->parents()->whereKey(auth()->id())->exists(),
            403
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateChildProfile(Request $request): array
    {
        $rules = self::CHILD_PROFILE_RULES;

        if (auth()->user()->isSuperAdmin()) {
            $rules['barangay_id'] = ['required', 'exists:barangays,id'];
        }

        $validated = $request->validate($rules);

        $validated['barangay_id'] = auth()->user()->isSuperAdmin()
            ? $validated['barangay_id']
            : auth()->user()->barangay_id;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function ensureNoDuplicateChild(array $validated, ?ChildProfile $ignore = null): void
    {
        $duplicate = ChildProfile::query()
            ->where('barangay_id', $validated['barangay_id'])
            ->whereDate('birthdate', $validated['birthdate'])
            ->where('first_name', $validated['first_name'])
            ->where('last_name', $validated['last_name'])
            ->when($ignore, fn (Builder $query) => $query->whereKeyNot($ignore->id))
            ->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'first_name' => 'Possible duplicate child found in this barangay with the same name and birthdate. Review the duplicates queue first.',
            ]);
        }
    }

    private function authorizeChildUpdate(ChildProfile $child): void
    {
        abort_unless(auth()->user()->canManageChildren(), 403);
        abort_if(auth()->user()->barangay_id === null, 403);
        abort_if($child->barangay_id !== auth()->user()->barangay_id, 403);
    }

    private function authorizeChildTransfer(ChildProfile $child): void
    {
        abort_unless(auth()->user()->isBarangayAdmin() || auth()->user()->isSuperAdmin(), 403);

        if (auth()->user()->isBarangayAdmin()) {
            abort_if(auth()->user()->barangay_id === null, 403);
            abort_if($child->barangay_id !== auth()->user()->barangay_id, 403);
        }
    }

    private function authorizeChildArchive(ChildProfile $child): void
    {
        $user = auth()->user();

        abort_unless($user->isSuperAdmin() || $user->accessibleBarangayIds()->contains($child->barangay_id), 403);
    }
}
