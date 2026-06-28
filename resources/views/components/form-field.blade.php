@props(['label', 'name', 'type' => 'text', 'options' => [], 'value' => null])

<label class="grid gap-2 text-sm">
    <span class="font-medium text-slate-800 dark:text-zinc-100">{{ $label }}</span>

    @if ($type === 'textarea')
        <textarea
            name="{{ $name }}"
            rows="3"
            class="app-input"
            {{ $attributes }}
        >{{ old($name, $value) }}</textarea>
    @elseif ($type === 'select')
        <select name="{{ $name }}" class="app-input" {{ $attributes }}>
            <option value="">Select {{ strtolower($label) }}</option>
            @foreach ($options as $optionValue => $text)
                <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $text }}</option>
            @endforeach
        </select>
    @else
        <input type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}" class="app-input" {{ $attributes }}>
    @endif

    @error($name)
        <span class="text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</span>
    @enderror
</label>
