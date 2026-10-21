@props([
    'name',
    'icon',
])

{{--
    Partner logo slot. Shows public/brand/{name}.svg once the official artwork is
    supplied (see public/brand/README.md); until then, a neutral icon. Partner
    marks are never copied from third-party logo sites.
--}}
@if (is_file(public_path("brand/{$name}.svg")))
    <img src="{{ asset("brand/{$name}.svg") }}" alt="" {{ $attributes->merge(['class' => 'h-3.5 w-auto']) }}>
@else
    <x-icon :name="$icon" {{ $attributes->merge(['class' => 'size-3.5']) }} />
@endif
