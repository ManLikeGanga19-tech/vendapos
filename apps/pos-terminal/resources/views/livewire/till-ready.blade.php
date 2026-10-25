<div class="mx-auto flex max-w-4xl flex-col gap-6 px-6 py-8">
    <header class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold tracking-widest text-primary uppercase">{{ $identity?->business_name }}</p>
            <h1 class="mt-1 text-3xl font-bold">{{ __('terminal.ready.title') }}</h1>
            <p class="mt-2 text-muted">{{ $identity?->device_name }} · {{ $identity?->branch_name }}</p>
        </div>
        <x-language-picker />
    </header>

    @if ($downloadPending)
        <p class="border border-line px-4 py-3 text-muted" role="status">{{ __('terminal.ready.download_pending') }}</p>
    @endif

    <dl class="grid grid-cols-2 gap-2">
        <div class="border border-line bg-panel px-6 py-5">
            <dt class="text-sm text-muted">{{ __('terminal.ready.items') }}</dt>
            <dd class="mt-1 text-4xl font-bold">{{ $itemCount }}</dd>
        </div>
        <div class="border border-line bg-panel px-6 py-5">
            <dt class="text-sm text-muted">{{ __('terminal.ready.staff') }}</dt>
            <dd class="mt-1 text-4xl font-bold">{{ $staffCount }}</dd>
        </div>
    </dl>

    <p class="text-muted">{{ __('terminal.ready.next') }}</p>

    <div>
        <button type="button" wire:click="syncNow" wire:loading.attr="disabled" class="key h-16 px-8 text-base">{{ __('terminal.ready.sync_now') }}</button>
    </div>
</div>
