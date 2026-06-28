<?php

namespace App\Http\Controllers;

use App\Models\VaccineSchedule;
use App\Models\VaccineType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'allowNewVaccine' => true,
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
            'allowNewVaccine' => false,
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

    public function toggleVaccine(VaccineType $vaccineType): RedirectResponse
    {
        $this->authorizeAdmin();

        $vaccineType->update(['active' => ! $vaccineType->active]);

        return to_route('vaccine-schedules.index')->with('status', 'Vaccine status updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?VaccineSchedule $schedule = null): array
    {
        $isCreate = $schedule === null;

        $validated = $request->validate([
            'vaccine_type_id' => [$isCreate ? 'nullable' : 'required', 'exists:vaccine_types,id', 'required_without:new_vaccine_name'],
            'new_vaccine_name' => [$isCreate ? 'nullable' : 'prohibited', 'string', 'max:255', 'required_without:vaccine_type_id', Rule::unique('vaccine_types', 'name')],
            'new_vaccine_code' => [$isCreate ? 'nullable' : 'prohibited', 'string', 'max:32', Rule::unique('vaccine_types', 'code')],
            'dose_number' => [
                'required',
                'integer',
                'min:1',
                'max:20',
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

        if ($isCreate && filled($validated['new_vaccine_name'] ?? null)) {
            $vaccine = VaccineType::create([
                'name' => $validated['new_vaccine_name'],
                'code' => $validated['new_vaccine_code'] ?: $this->uniqueVaccineCode($validated['new_vaccine_name']),
                'active' => true,
            ]);

            $validated['vaccine_type_id'] = $vaccine->id;
        }

        $request->validate([
            'dose_number' => [
                Rule::unique('vaccine_schedules', 'dose_number')
                    ->where('vaccine_type_id', (int) $validated['vaccine_type_id'])
                    ->ignore($schedule?->id),
            ],
        ]);

        $validated['active'] = $request->boolean('active');

        unset($validated['new_vaccine_name'], $validated['new_vaccine_code']);

        return $validated;
    }

    private function uniqueVaccineCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '_')) ?: 'VACCINE';
        $code = Str::limit($base, 28, '');
        $suffix = 2;

        while (VaccineType::where('code', $code)->exists()) {
            $code = Str::limit($base, 25, '').'_'.$suffix;
            $suffix++;
        }

        return $code;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
    }
}
