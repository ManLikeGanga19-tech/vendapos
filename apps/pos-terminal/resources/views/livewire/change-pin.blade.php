<div class="mx-auto flex max-w-4xl flex-col gap-6 px-6 py-8">
    <x-idle-sign-out />

    <header class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold tracking-widest text-primary uppercase">{{ __('terminal.pin_change.title') }}</p>
            <h1 class="mt-1 text-3xl font-bold">{{ __("terminal.pin_change.steps.{$step}", ['length' => $staff?->pin_length]) }}</h1>
            <p class="mt-2 text-muted">{{ $staff?->name }} · {{ __('terminal.pin_change.online_only') }}</p>
        </div>
        <x-language-picker />
    </header>

    @if ($staff)
        <x-pin-pad :length="$staff->pin_length" :filled="strlen($pin)" :message="$message" />
    @endif
</div>
