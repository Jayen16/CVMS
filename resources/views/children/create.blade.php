<x-layouts::app :title="__('New child')">
    <div class="app-page">
        <div class="mx-auto w-full max-w-3xl">
            <div class="mb-6">
                <p class="eyebrow">Registry</p>
                <h1 class="page-title">Create child profile</h1>
                <p class="page-subtitle">Add the child's demographic and guardian information before recording vaccinations.</p>
            </div>

            <form method="POST" action="{{ route('children.store') }}" class="app-panel grid gap-4">
                @csrf

                @if (auth()->user()->isAdmin())
                    <x-form-field label="Barangay" name="barangay_id" type="select" :options="$barangays->pluck('name', 'id')" />
                @else
                    <div class="rounded-lg border border-teal-100 bg-teal-50 p-3 text-sm text-teal-950 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-100">
                        Assigned barangay: <span class="font-semibold">{{ auth()->user()->barangay?->name }}</span>
                    </div>
                @endif

                <div class="grid gap-4 md:grid-cols-3">
                    <x-form-field label="First name" name="first_name" />
                    <x-form-field label="Middle name" name="middle_name" />
                    <x-form-field label="Last name" name="last_name" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <x-form-field label="Birthdate" name="birthdate" type="date" />
                    <x-form-field label="Sex" name="sex" type="select" :options="['female' => 'Female', 'male' => 'Male']" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <x-form-field label="Guardian name" name="guardian_name" />
                    <x-form-field label="Guardian contact" name="guardian_contact" />
                </div>

                <x-form-field label="Address" name="address" type="textarea" />

                <div class="flex justify-end gap-2">
                    <a href="{{ route('children.index') }}" class="app-button-secondary">Cancel</a>
                    <button class="app-button-primary">Save child</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
