<?php

namespace App\Http\Controllers;

use App\Models\VaccineSchedule;
use App\Models\VaccineType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VaccineScheduleController extends Controller
{
    public function index(): View
    {
        $this->authorizeAdmin();

        return view('vaccine-schedules.index', [
            'vaccines' => VaccineType::query()
                ->with(['schedules' => fn ($query) => $query->orderBy('dose_number')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdmin();

        return view('vaccine-schedules.form', [
            'schedule' => new VaccineSchedule(['active' => true]),
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'indications' => VaccineSchedule::indicationOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        VaccineSchedule::create($this->validated($request));

        return to_route('vaccine-schedules.index')->with('status', 'Vaccine schedule dose created.');
    }

    public function edit(VaccineSchedule $vaccineSchedule): View
    {
        $this->authorizeAdmin();

        return view('vaccine-schedules.form', [
            'schedule' => $vaccineSchedule,
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'indications' => VaccineSchedule::indicationOptions(),
        ]);
    }

    public function update(Request $request, VaccineSchedule $vaccineSchedule): RedirectResponse
    {
        $this->authorizeAdmin();

        $vaccineSchedule->update($this->validated($request, $vaccineSchedule));

        return to_route('vaccine-schedules.index')->with('status', 'Vaccine schedule dose updated.');
    }

    public function toggle(VaccineSchedule $vaccineSchedule): RedirectResponse
    {
        $this->authorizeAdmin();

        $vaccineSchedule->update(['active' => ! $vaccineSchedule->active]);

        return to_route('vaccine-schedules.index')->with('status', 'Vaccine schedule dose status updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?VaccineSchedule $schedule = null): array
    {
        $validated = $request->validate([
            'vaccine_type_id' => ['required', 'exists:vaccine_types,id'],
            'dose_number' => [
                'required',
                'integer',
                'min:1',
                'max:20',
                Rule::unique('vaccine_schedules', 'dose_number')
                    ->where('vaccine_type_id', $request->integer('vaccine_type_id'))
                    ->ignore($schedule?->id),
            ],
            'age_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'age_weeks' => ['required', 'integer', 'min:0', 'max:520'],
            'age_months' => ['required', 'integer', 'min:0', 'max:240'],
            'age_years' => ['required', 'integer', 'min:0', 'max:20'],
            'label' => ['required', 'string', 'max:255'],
            'indication' => ['required', Rule::in(array_keys(VaccineSchedule::indicationOptions()))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'active' => ['nullable', 'boolean'],
        ]);

        $validated['active'] = $request->boolean('active');

        return $validated;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }
}
