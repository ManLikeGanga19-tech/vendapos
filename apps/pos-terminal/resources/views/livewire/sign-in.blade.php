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
        <x-pin-pad :length="$chosen->pin_length" :filled="strlen($pin)" :message="$message" />
    @endif
</div>
