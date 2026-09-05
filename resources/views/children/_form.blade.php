@php
    $isEditing = $child->exists;
@endphp

<form method="POST" action="{{ $isEditing ? route('children.update', $child) : route('children.store') }}" class="app-panel grid gap-6 {{ $formClass ?? '' }}">
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    @if (auth()->user()->isSuperAdmin())
        <x-form-field label="Barangay" name="barangay_id" type="select" :options="$barangays->pluck('name', 'id')" :value="$child->barangay_id" />
    @else
        <div class="rounded-lg border border-teal-100 bg-teal-50 p-3 text-sm text-teal-950 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-100">
            Assigned barangay: <span class="font-semibold">{{ $child->barangay?->name ?? auth()->user()->barangay?->name }}</span>
        </div>
    @endif

    <div class="border-t border-slate-200 pt-5 dark:border-zinc-800">
        <div class="mb-4">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Child details</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Enter the child’s legal name and basic demographic information.</p>
        </div>
    <div class="grid gap-4 md:grid-cols-3">
        <x-form-field label="First name" name="first_name" :value="$child->first_name" />
        <x-form-field label="Middle name" name="middle_name" :value="$child->middle_name" />
        <x-form-field label="Last name" name="last_name" :value="$child->last_name" />
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <x-form-field label="Birthdate" name="birthdate" type="date" :value="$child->birthdate?->toDateString()" />
        <x-form-field label="Sex" name="sex" type="select" :options="['female' => 'Female', 'male' => 'Male']" :value="$child->sex" />
    </div>
    </div>

    <div class="border-t border-slate-200 pt-5 dark:border-zinc-800">
    <x-form-field label="Address" name="address" type="textarea" :value="$child->address" />
    </div>

    <div class="flex flex-col-reverse justify-between gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center dark:border-zinc-800">
        <p class="text-xs text-slate-500 dark:text-zinc-400">You can add vaccination records after saving this profile.</p>
        <div class="flex justify-end gap-2">
        <a href="{{ $isEditing ? route('children.show', $child) : route('children.index') }}" class="app-button-secondary">Cancel</a>
        <button class="app-button-primary px-5">{{ $isEditing ? 'Save changes' : 'Save child' }}</button>
        </div>
    </div>
</form>
