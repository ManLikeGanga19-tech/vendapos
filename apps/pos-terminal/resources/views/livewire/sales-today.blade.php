@php
    use App\Livewire\StatusBar;
    use VendaPOS\Core\ValueObjects\Money;

    $money = fn (int $cents): string => Money::fromCents($cents)->format();
    $time = fn ($moment): string => $moment->setTimezone(StatusBar::TIMEZONE)->format('H:i');
@endphp
<div class="mx-auto flex h-[calc(100vh-2.4rem)] max-w-5xl flex-col gap-4 px-6 py-6">
    <x-idle-sign-out hold="['pin'].includes($wire.step)" />

    <header class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">{{ __('terminal.sales.title') }}</h1>
            <p class="mt-1 text-muted">{{ $online ? __('terminal.sales.all_tills') : __('terminal.sales.offline') }}</p>
        </div>
        @if ($step !== 'done')
            <button type="button" wire:click="back" class="key h-12 px-4 text-base">{{ __('terminal.sale.back') }}</button>
        @endif
    </header>

    @if ($message)
        <p class="border border-danger px-4 py-3 text-danger" role="alert">{{ $message }}</p>
    @endif

    @if ($step === 'list')
        <div class="flex gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('terminal.sales.search') }}" autocomplete="off"
                class="h-12 flex-1 border border-line bg-panel px-3 font-mono text-white">
            <button type="button" wire:click="refresh" class="key h-12 px-4 text-base">{{ __('terminal.sales.refresh') }}</button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto border border-line">
            @forelse ($sales as $row)
                <button type="button" wire:key="sale-{{ $row->id }}" wire:click="select('{{ $row->id }}')"
                    class="flex w-full items-center gap-4 border-b border-line px-4 py-3 text-left hover:bg-panel">
                    <span class="w-32 font-mono">{{ $row->receiptNumber ?? '—' }}</span>
                    <span class="w-14 font-mono text-muted">{{ $time($row->placedAt) }}</span>
                    <span class="min-w-0 flex-1 truncate">{{ $staffNames[$row->staffId] ?? '—' }}</span>
                    <span @class(['w-24 text-sm', 'text-danger' => $row->isVoided(), 'text-muted' => ! $row->isVoided()])>
                        {{ $row->isVoided() ? __('terminal.sales.voided') : ($row->mpesaCents() > 0 ? 'M-Pesa' : __('terminal.receipt.cash')) }}
                    </span>
                    <span @class(['w-28 text-right font-mono', 'line-through text-muted' => $row->isVoided()])>{{ $money($row->totalCents) }}</span>
                </button>
            @empty
                <p class="px-4 py-8 text-center text-muted">{{ $search === '' ? __('terminal.sales.none') : __('terminal.sales.no_match') }}</p>
            @endforelse
        </div>
    @elseif ($sale === null && $step !== 'done')
        <p class="text-muted">{{ __('terminal.sales.gone') }}</p>
        <button type="button" wire:click="finish" class="key h-14 w-full text-base">{{ __('terminal.sales.back_to_list') }}</button>
    @elseif ($step === 'sale')
        <section class="flex flex-col gap-4 border border-line bg-panel p-5">
            <div class="flex items-baseline justify-between gap-4">
                <p class="font-mono text-2xl font-bold">{{ $sale->receiptNumber ?? '—' }}</p>
                <p class="text-muted">{{ $time($sale->placedAt) }} · {{ $staffNames[$sale->staffId] ?? '—' }}</p>
            </div>
            <div class="flex flex-col gap-1 font-mono">
                @foreach ($sale->lines as $line)
                    <div class="flex justify-between gap-4"><span>{{ $line['quantity'] }} × {{ $line['name'] }}</span><span>{{ $money($line['line_total_cents']) }}</span></div>
                @endforeach
                <div class="mt-2 flex justify-between gap-4 border-t border-line pt-2 font-bold"><span>{{ __('terminal.receipt.total') }}</span><span>KES {{ $money($sale->totalCents) }}</span></div>
                @foreach ($sale->tenders as $tender)
                    <div class="flex justify-between gap-4 text-muted"><span>{{ $tender['method'] === 'cash' ? __('terminal.receipt.cash') : trim('M-Pesa '.$tender['mpesa_reference']) }}</span><span>{{ $money($tender['amount_cents']) }}</span></div>
                @endforeach
            </div>
            @if ($sale->isVoided())
                <p class="text-danger">{{ __('terminal.sales.already_voided', ['reason' => $sale->voidReason ? __("terminal.void_reasons.{$sale->voidReason}") : '—']) }}</p>
            @elseif (! $online && ! $sale->mine)
                <p class="text-muted">{{ __('terminal.sales.refused.offline') }}</p>
            @else
                <button type="button" wire:click="startVoid" class="key h-16 w-full border-danger text-danger">{{ __('terminal.sales.void') }}</button>
            @endif
        </section>
    @elseif ($step === 'reason')
        <section class="flex flex-col gap-3">
            <p class="text-lg">{{ __('terminal.sales.why', ['number' => $sale->receiptNumber ?? '—']) }}</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($reasons as $option)
                    <button type="button" wire:click="pickReason('{{ $option->value }}')"
                        @class(['key h-16 text-base', 'border-primary text-primary' => $reason === $option->value])>{{ __("terminal.void_reasons.{$option->value}") }}</button>
                @endforeach
            </div>
            @if ($reason === 'other')
                <form wire:submit="confirmReason" class="flex flex-col gap-2">
                    <label class="flex flex-col gap-2">
                        <span class="text-sm text-muted">{{ __('terminal.sales.note') }}</span>
                        <input type="text" wire:model="note" maxlength="{{ \VendaPOS\Core\Data\VoidData::NOTE_MAX }}" autocomplete="off" autofocus
                            class="h-12 border border-line bg-panel px-3 text-white">
                    </label>
                    @error('note') <p class="text-sm text-danger">{{ $message }}</p> @enderror
                    <button type="submit" class="key-primary h-14">{{ __('terminal.sales.next') }}</button>
                </form>
            @endif
        </section>
    @elseif ($step === 'approver')
        <section class="flex flex-col gap-3">
            <p class="text-lg">{{ __('terminal.sales.who_approves') }}</p>
            @if ($approvers->isEmpty())
                <p class="text-muted">{{ __('terminal.sales.no_approvers') }}</p>
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
        <p class="text-center text-lg">{{ __('terminal.sales.approve_pin', ['name' => $approver->name, 'number' => $sale->receiptNumber ?? '—']) }}</p>
        <x-pin-pad :length="$approver->pin_length" :filled="strlen($pin)" />
    @elseif ($step === 'done' && $done)
        <div class="flex flex-1 flex-col items-center justify-center gap-6 text-center" role="status">
            <p class="text-sm tracking-widest text-ok uppercase">{{ __('terminal.sales.done', ['number' => $done['receipt'] ?? '—']) }}</p>
            @if ($done['cash_cents'] > 0)
                <div>
                    <p class="text-lg">{{ __('terminal.sales.give_back') }}</p>
                    <p class="font-mono text-5xl font-bold">{{ $money($done['cash_cents']) }}</p>
                </div>
            @endif
            @if ($done['mpesa_cents'] > 0)
                <p class="max-w-xl text-lg">{{ __('terminal.sales.mpesa_back', ['amount' => 'KES '.$money($done['mpesa_cents'])]) }}</p>
            @endif
            <div class="flex w-full max-w-md flex-col gap-2">
                <button type="button" wire:click="finish" class="key-primary h-16" autofocus>{{ __('terminal.sales.back_to_list') }}</button>
                <a href="{{ route('home') }}" class="key h-12 text-base">{{ __('terminal.sales.new_sale') }}</a>
            </div>
        </div>
    @endif
</div>
