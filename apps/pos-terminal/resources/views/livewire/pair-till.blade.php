<div class="mx-auto flex min-h-screen max-w-4xl flex-col gap-6 px-6 py-8">
    <header class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold tracking-widest text-primary uppercase">VendaPOS</p>
            <h1 class="mt-1 text-3xl font-bold">{{ __('terminal.pair.title') }}</h1>
            <p class="mt-2 max-w-xl text-muted">{{ __('terminal.pair.help') }}</p>
        </div>
        <x-language-picker />
    </header>

    @if ($removed)
        <p class="border border-danger px-4 py-3 text-danger" role="alert">{{ __('terminal.pair.removed') }}</p>
    @endif

    {{-- A physical keyboard works anywhere on the screen; the on-screen keys are for touch. --}}
    <form
        wire:submit="pair"
        class="flex flex-col gap-6"
        x-data
        x-on:keydown.window="
            if ($event.ctrlKey || $event.altKey || $event.metaKey) return;
            if ($event.key === 'Backspace') { $event.preventDefault(); $wire.backspace(); }
            else if ($event.key === 'Escape') { $wire.clear(); }
            else if (/^[a-z0-9]$/i.test($event.key)) { $wire.press($event.key.toUpperCase()); }
        "
    >
        <div class="flex flex-col gap-2">
            <span class="text-sm text-muted" id="code-label">{{ __('terminal.pair.code') }}</span>
            <output aria-labelledby="code-label" aria-live="polite" class="border border-line bg-panel px-6 py-5 text-center font-mono text-5xl tracking-[0.3em]">{{ $display }}</output>
        </div>

        @if ($error)
            <p class="border border-danger px-4 py-3 text-danger" role="alert">{{ $error }}</p>
        @endif

        <div class="grid grid-cols-10 gap-2">
            @foreach ($keys as $key)
                <button type="button" wire:click="press('{{ $key }}')" class="key h-16 font-mono">{{ $key }}</button>
            @endforeach
        </div>

        <div class="grid grid-cols-4 gap-2">
            <button type="button" wire:click="clear" class="key h-16 text-base">{{ __('terminal.pair.clear') }}</button>
            <button type="button" wire:click="backspace" class="key h-16 text-base">{{ __('terminal.pair.backspace') }}</button>
            <button type="submit" class="key-primary col-span-2 h-16" @disabled(! $complete) wire:loading.attr="disabled" wire:target="pair">
                <span wire:loading.remove wire:target="pair">{{ __('terminal.pair.submit') }}</span>
                <span wire:loading wire:target="pair">{{ __('terminal.pair.working') }}</span>
            </button>
        </div>
    </form>
</div>
