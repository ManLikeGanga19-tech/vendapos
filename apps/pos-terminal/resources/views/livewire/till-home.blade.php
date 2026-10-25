<div class="mx-auto flex max-w-4xl flex-col gap-6 px-6 py-8">
    <x-idle-sign-out />

    <header class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold tracking-widest text-primary uppercase">{{ $identity?->business_name }}</p>
            <h1 class="mt-1 text-3xl font-bold">{{ __('terminal.home.signed_in_as', ['name' => $staff?->name]) }}</h1>
            <p class="mt-2 text-muted">{{ $staff ? __("terminal.roles.{$staff->role}") : '' }} · {{ $identity?->device_name }} · {{ $identity?->branch_name }}</p>
        </div>
        <x-language-picker />
    </header>

    @if (session('notice'))
        <p class="border border-ok px-4 py-3 text-ok" role="status">{{ session('notice') }}</p>
    @endif

    <dl class="grid grid-cols-2 gap-2">
        <div class="border border-line bg-panel px-6 py-5">
            <dt class="text-sm text-muted">{{ __('terminal.home.items') }}</dt>
            <dd class="mt-1 text-4xl font-bold">{{ $itemCount }}</dd>
        </div>
        <div class="border border-line bg-panel px-6 py-5">
            <dt class="text-sm text-muted">{{ __('terminal.home.staff') }}</dt>
            <dd class="mt-1 text-4xl font-bold">{{ $staffCount }}</dd>
        </div>
    </dl>

    <p class="text-muted">{{ __('terminal.home.next') }}</p>

    <div class="grid grid-cols-3 gap-2">
        <a href="{{ route('pin') }}" class="key h-16 text-base">{{ __('terminal.home.change_pin') }}</a>
        <button type="button" wire:click="signOut" class="key h-16 text-base">{{ __('terminal.home.switch') }}</button>
        <button type="button" wire:click="signOut" class="key h-16 text-base">{{ __('terminal.home.sign_out') }}</button>
    </div>
</div>
