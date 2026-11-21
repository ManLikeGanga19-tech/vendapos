<div class="mx-auto flex h-[calc(100vh-2.4rem)] max-w-3xl flex-col gap-4 px-6 py-6">
    <x-idle-sign-out hold="['pin'].includes($wire.step)" />

    <header class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="truncate text-3xl font-bold">{{ __('terminal.tickets.void.title', ['name' => $line?->name ?? '—']) }}</h1>
            <p class="mt-1 text-muted">{{ $ticket?->title() }}</p>
        </div>
        <button type="button" wire:click="back" class="key h-12 px-4 text-base">{{ __('terminal.sale.back') }}</button>
    </header>

    @if ($message && $step !== 'pin')
        <p class="border border-danger px-4 py-3 text-danger" role="alert">{{ $message }}</p>
    @endif

    @if ($line === null)
        <p class="text-muted">{{ __('terminal.tickets.refused.unknown_line') }}</p>
    @elseif ($step === 'quantity')
        <section class="flex flex-col items-center gap-6">
            <p class="text-lg">{{ __('terminal.tickets.void.how_many', ['name' => $line->name, 'left' => $left]) }}</p>
            <div class="flex items-center gap-6">
                <button type="button" wire:click="less" class="key size-20 text-4xl" aria-label="{{ __('terminal.tickets.void.less') }}" @disabled($quantity <= 1)>−</button>
                <p class="w-24 text-center font-mono text-6xl font-bold" aria-live="polite">{{ $quantity }}</p>
                <button type="button" wire:click="more" class="key size-20 text-4xl" aria-label="{{ __('terminal.tickets.void.more') }}" @disabled($quantity >= $left)>+</button>
            </div>
            <button type="button" wire:click="confirmQuantity" class="key-primary h-16 w-full max-w-md">{{ __('terminal.tickets.void.next') }}</button>
        </section>
    @elseif ($step === 'reason')
        <section class="flex flex-col gap-3">
            <p class="text-lg">{{ __('terminal.tickets.void.why', ['name' => $quantity.' × '.$line->name]) }}</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($reasons as $option)
                    <button type="button" wire:click="pickReason('{{ $option->value }}')"
                        @class(['key h-16 text-base', 'border-primary text-primary' => $reason === $option->value])>{{ __("terminal.void_reasons.{$option->value}") }}</button>
                @endforeach
            </div>
            @if ($reason === 'other')
                <form wire:submit="confirmReason" class="flex flex-col gap-2">
                    <label class="flex flex-col gap-2">
                        <span class="text-sm text-muted">{{ __('terminal.tickets.void.note') }}</span>
                        <input type="text" wire:model="note" maxlength="{{ \VendaPOS\Core\Data\VoidData::NOTE_MAX }}" autocomplete="off" autofocus
                            class="h-12 border border-line bg-panel px-3 text-white">
                    </label>
                    @error('note') <p class="text-sm text-danger">{{ $message }}</p> @enderror
                    <button type="submit" class="key-primary h-14">{{ __('terminal.tickets.void.next') }}</button>
                </form>
            @endif
        </section>
    @elseif ($step === 'approver')
        <section class="flex flex-col gap-3">
            <p class="text-lg">{{ __('terminal.tickets.void.who_approves') }}</p>
            @if ($approvers->isEmpty())
                <p class="text-muted">{{ __('terminal.tickets.void.no_approvers') }}</p>
            @endif
            <div class="grid grid-cols-3 gap-2">
                @foreach ($approvers as $member)
                    <button type="button" wire:key="approver-{{ $member->id }}" wire:click="chooseApprover('{{ $member->id }}')" class="key h-24 flex-col items-start justify-center px-5 text-left">
                        <span class="text-xl">{{ $member->name }}</span>
                        <span class="text-sm font-normal text-muted">{{ __("terminal.roles.{$member->role}") }}</span>
                    </button>
                @endforeach
            </div>
        </section>
    @elseif ($step === 'pin' && $approver)
        <p class="text-center text-lg">{{ __('terminal.tickets.void.approve_pin', ['name' => $approver->name, 'quantity' => $quantity, 'item' => $line->name]) }}</p>
        <x-pin-pad :length="$approver->pin_length" :filled="strlen($pin)" :message="$message" />
    @endif
</div>
