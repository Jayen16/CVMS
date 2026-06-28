@php
    $isEditing = $child->exists;
@endphp

<form method="POST" action="{{ $isEditing ? route('children.update', $child) : route('children.store') }}" class="app-panel grid gap-4">
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

    <div class="grid gap-4 md:grid-cols-3">
        <x-form-field label="First name" name="first_name" :value="$child->first_name" />
        <x-form-field label="Middle name" name="middle_name" :value="$child->middle_name" />
        <x-form-field label="Last name" name="last_name" :value="$child->last_name" />
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <x-form-field label="Birthdate" name="birthdate" type="date" :value="$child->birthdate?->toDateString()" />
        <x-form-field label="Sex" name="sex" type="select" :options="['female' => 'Female', 'male' => 'Male']" :value="$child->sex" />
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <x-form-field label="Guardian name" name="guardian_name" :value="$child->guardian_name" />
        <x-form-field label="Guardian contact" name="guardian_contact" :value="$child->guardian_contact" />
    </div>

    <x-form-field label="Address" name="address" type="textarea" :value="$child->address" />

    <div class="flex justify-end gap-2">
        <a href="{{ $isEditing ? route('children.show', $child) : route('children.index') }}" class="app-button-secondary">Cancel</a>
        <button class="app-button-primary">{{ $isEditing ? 'Save changes' : 'Save child' }}</button>
    </div>
</form>
