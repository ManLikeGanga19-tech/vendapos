@props([
    'name',
    'label',
    'hint' => null,
    'optional' => false,
])

@php
    $error = $errors->first($name);
@endphp

{{-- Label, hint and error wrapper; the control in the slot uses id="{{ $name }}". --}}
<div {{ $attributes }}>
    <label for="{{ $name }}" class="mb-1.5 flex items-baseline justify-between gap-2 text-sm font-medium text-ink">
        {{ $label }}
        @if ($optional)
            <span class="text-xs font-normal text-subtle">{{ __('demo.form.optional') }}</span>
        @endif
    </label>

    {{ $slot }}

    @if ($hint)
        <p id="{{ $name }}-hint" class="mt-1.5 text-sm text-muted">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $name }}-error" class="mt-1.5 flex items-center gap-1.5 text-sm text-danger">
            <x-icon name="x" class="size-4" />{{ $error }}
        </p>
    @endif
</div>
