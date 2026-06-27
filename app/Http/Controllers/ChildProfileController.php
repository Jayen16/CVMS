<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\VaccineType;
use App\Services\ImmunizationSuggestionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChildProfileController extends Controller
{
    public function index(): View
    {
        return view('children.index', [
            'children' => $this->visibleChildren()
                ->with(['barangay', 'vaccinations'])
                ->latest()
                ->paginate(12),
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

    public function store(Request $request): RedirectResponse
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

        $child = ChildProfile::create($validated);

        return to_route('children.show', $child)->with('status', 'Child profile created.');
    }

    public function show(ChildProfile $child, ImmunizationSuggestionService $suggestions): View
    {
        $this->authorizeChild($child);

        $child->load(['barangay', 'parents', 'vaccinations.vaccineType', 'vaccinations.recorder', 'vaccinations.submitter', 'vaccinations.verifier']);

        return view('children.show', [
            'child' => $child,
            'suggestion' => $suggestions->suggestNextDose($child),
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
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
