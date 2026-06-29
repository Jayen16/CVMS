    <div class="app-page">
        <div class="mx-auto w-full max-w-3xl">
            <div class="mb-6">
                <a href="{{ route('vaccine-schedules.index') }}" class="text-sm text-teal-700 hover:underline dark:text-teal-300">Back to schedules</a>
                <p class="eyebrow mt-3">Administration</p>
                <h1 class="page-title">{{ $schedule->exists ? 'Edit dose rule' : 'Add dose rule' }}</h1>
                <p class="page-subtitle">This controls guideline timing for suggestions, reminders, and timeline markers.</p>
            </div>

            <form method="POST" action="{{ $schedule->exists ? route('vaccine-schedules.update', $schedule) : route('vaccine-schedules.store') }}" class="app-panel grid gap-4">
                @csrf
                @if ($schedule->exists)
                    @method('PUT')
                @endif

                <label class="grid gap-2 text-sm">
                    <span class="font-medium text-slate-800 dark:text-zinc-100">Vaccine</span>
                    <select name="vaccine_type_id" class="app-input">
                        <option value="">{{ $allowNewVaccine ? 'Select existing vaccine or add new below' : 'Select vaccine' }}</option>
                        @foreach ($vaccines as $vaccine)
                            <option value="{{ $vaccine->id }}" @selected((int) old('vaccine_type_id', $schedule->vaccine_type_id) === $vaccine->id)>{{ $vaccine->name }}</option>
                        @endforeach
                    </select>
                    @error('vaccine_type_id') <span class="text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </label>

                @if ($allowNewVaccine)
                    <div class="rounded-lg border border-dashed border-teal-300 bg-teal-50/60 p-4 dark:border-teal-800 dark:bg-teal-950/40">
                        <h2 class="text-sm font-semibold text-slate-950 dark:text-white">Add new vaccine</h2>
                        <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">Use this when the vaccine is not in the existing choices. Leave these blank when selecting an existing vaccine.</p>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <x-form-field label="New vaccine name" name="new_vaccine_name" />
                            <x-form-field label="New vaccine code" name="new_vaccine_code" />
                        </div>
                    </div>
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    <x-form-field label="Dose number" name="dose_number" type="number" :value="$schedule->dose_number" />
                    <x-form-field label="Label" name="label" :value="$schedule->label" />
                </div>

                <label class="grid gap-2 text-sm">
                    <span class="font-medium text-slate-800 dark:text-zinc-100">Indication</span>
                    <select name="indication" class="app-input">
                        @foreach ($indications as $value => $label)
                            <option value="{{ $value }}" @selected(old('indication', $schedule->indication ?? 'routine_vaccination') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('indication') <span class="text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </label>

                <div class="grid gap-4 md:grid-cols-4">
                    <x-form-field label="Years after birth" name="age_years" type="number" :value="$schedule->age_years ?? 0" />
                    <x-form-field label="Months after birth" name="age_months" type="number" :value="$schedule->age_months ?? 0" />
                    <x-form-field label="Weeks after birth" name="age_weeks" type="number" :value="$schedule->age_weeks ?? 0" />
                    <x-form-field label="Days after birth" name="age_days" type="number" :value="$schedule->age_days ?? 0" />
                </div>

                <x-form-field label="Notes" name="notes" type="textarea" :value="$schedule->notes" />

                <label class="flex items-center gap-3 text-sm font-medium text-slate-800 dark:text-zinc-100">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $schedule->active ?? true)) class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                    Active
                </label>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('vaccine-schedules.index') }}" class="app-button-secondary">Cancel</a>
                    <button class="app-button-primary">Save schedule</button>
                </div>
            </form>
        </div>
    </div>
