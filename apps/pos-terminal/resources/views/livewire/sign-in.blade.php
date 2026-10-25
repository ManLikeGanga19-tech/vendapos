<div class="mx-auto flex max-w-4xl flex-col gap-6 px-6 py-8">
    <header class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold tracking-widest text-primary uppercase">{{ $identity?->business_name }}</p>
            <h1 class="mt-1 text-3xl font-bold">{{ $chosen ? $chosen->name : __('terminal.sign_in.title') }}</h1>
            <p class="mt-2 text-muted">
                {{ $chosen ? __("terminal.roles.{$chosen->role}").' · '.__('terminal.sign_in.enter_pin') : __('terminal.sign_in.choose') }}
            </p>
        </div>
        <x-language-picker />
    </header>

    @if ($downloadPending)
        <p class="border border-line px-4 py-3 text-muted" role="status">{{ __('terminal.sign_in.download_pending') }}</p>
    @endif

    @if ($staff->isEmpty())
        <div class="flex flex-col gap-4 border border-line bg-panel px-6 py-8">
            <h2 class="text-xl font-semibold">{{ __('terminal.sign_in.no_staff') }}</h2>
            <p class="text-muted">{{ __('terminal.sign_in.no_staff_help') }}</p>
            <div>
                <button type="button" wire:click="syncNow" wire:loading.attr="disabled" class="key h-16 px-8 text-base">{{ __('terminal.sign_in.sync_now') }}</button>
            </div>
        </div>
    @elseif (! $chosen)
        <div class="grid grid-cols-3 gap-2">
            @foreach ($staff as $member)
                <button type="button" wire:click="choose('{{ $member->id }}')" class="key h-24 flex-col items-start justify-center px-5 text-left">
                    <span class="text-xl">{{ $member->name }}</span>
                    <span class="text-sm font-normal text-muted">{{ __("terminal.roles.{$member->role}") }}</span>
                </button>
            @endforeach
        </div>
    @else
        {{-- Digits from a physical keyboard work too; Escape goes back to the names. --}}
        <div
            class="mx-auto flex w-full max-w-md flex-col gap-6"
            x-data
            x-on:keydown.window="
                if ($event.ctrlKey || $event.altKey || $event.metaKey) return;
                if (/^[0-9]$/.test($event.key)) { $wire.press($event.key); }
                else if ($event.key === 'Backspace') { $event.preventDefault(); $wire.backspace(); }
                else if ($event.key === 'Escape') { $wire.back(); }
            "
        >
            <div class="flex justify-center gap-4" aria-label="{{ __('terminal.sign_in.enter_pin') }}">
                @for ($i = 0; $i < $chosen->pin_length; $i++)
                    <span @class([
                        'inline-block size-5 border-2',
                        'border-primary bg-primary' => $i < strlen($pin),
                        'border-line' => $i >= strlen($pin),
                    ])></span>
                @endfor
            </div>

            @if ($message)
                <p class="border border-danger px-4 py-3 text-center text-danger" role="alert">{{ $message }}</p>
            @endif

            <div class="grid grid-cols-3 gap-2" wire:loading.class="opacity-60" wire:target="press">
                @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $digit)
                    <button type="button" wire:click="press('{{ $digit }}')" class="key h-20 font-mono text-3xl">{{ $digit }}</button>
                @endforeach
                <button type="button" wire:click="back" class="key h-20 text-base">{{ __('terminal.sign_in.back') }}</button>
                <button type="button" wire:click="press('0')" class="key h-20 font-mono text-3xl">0</button>
                <button type="button" wire:click="backspace" class="key h-20 text-base">{{ __('terminal.pair.backspace') }}</button>
            </div>
        </div>
    @endif
</div>
