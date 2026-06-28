<?php

namespace App\Http\Controllers;

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
    public function index(Request $request): View
    {
        $vaccineTypeId = $request->integer('vaccine_type_id') ?: null;

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

    public function create(): View
    {
        abort_if(auth()->user()->isParent(), 403);
        abort_if(auth()->user()->isNurse() && auth()->user()->barangay_id === null, 403, 'A nurse must be assigned to a barangay before creating child profiles.');

        return view('children.create', [
            'barangays' => Barangay::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, OfflineSyncService $offlineSync): RedirectResponse
    {
        abort_if(auth()->user()->isParent(), 403);
        abort_if(auth()->user()->isNurse() && auth()->user()->barangay_id === null, 403);

        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date', 'before_or_equal:today'],
            'sex' => ['required', 'in:female,male'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_contact' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];

        if (auth()->user()->isAdmin()) {
            $rules['barangay_id'] = ['required', 'exists:barangays,id'];
        }

        $validated = $request->validate($rules);

        $validated['barangay_id'] = auth()->user()->isAdmin()
            ? $validated['barangay_id']
            : auth()->user()->barangay_id;
        $validated['created_by'] = auth()->id();

        $duplicate = ChildProfile::query()
            ->where('barangay_id', $validated['barangay_id'])
            ->whereDate('birthdate', $validated['birthdate'])
            ->where('first_name', $validated['first_name'])
            ->where('last_name', $validated['last_name'])
            ->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'first_name' => 'Possible duplicate child found in this barangay with the same name and birthdate. Review the duplicates queue first.',
            ]);
        }

        $child = ChildProfile::create($validated);
        $offlineSync->queueUpsert($child->load(['barangay', 'creator']));

        return to_route('children.show', $child)->with('status', 'Child profile created.');
    }

    public function show(ChildProfile $child, ImmunizationSuggestionService $suggestions): View
    {
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
                ->first(fn (VaccinationRecord $record) => $record->id === request()->integer('edit_record'));

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

        if (auth()->user()->isNurse()) {
            $query->where('barangay_id', auth()->user()->barangay_id);
        }

        if (auth()->user()->isParent()) {
            $query->whereHas('parents', fn (Builder $query) => $query->whereKey(auth()->id()));
        }

        return $query;
    }

    private function authorizeChild(ChildProfile $child): void
    {
        abort_if(auth()->user()->isNurse() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(
            auth()->user()->isParent() && ! $child->parents()->whereKey(auth()->id())->exists(),
            403
        );
    }
}
