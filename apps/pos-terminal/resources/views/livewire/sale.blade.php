<div
    class="flex h-[calc(100vh-2.4rem)] flex-col"
    x-data="{
        buffer: '',
        last: 0,
        {{-- A scanner types fast and ends with Enter; people type slowly. Ignored while a field has focus. --}}
        key(event) {
            if (event.ctrlKey || event.altKey || event.metaKey) return;
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
            const now = Date.now();
            if (now - this.last > 60) this.buffer = '';
            this.last = now;
            if (event.key === 'Enter') {
                if (this.buffer.length >= 4) { $wire.scan(this.buffer); }
                else if ($wire.step === 'pay') { $wire.complete(); }
                this.buffer = '';
                return;
            }
            if (event.key.length === 1) this.buffer += event.key;
            if ($wire.step === 'pay' && /^[0-9]$/.test(event.key)) { setTimeout(() => { if (this.buffer.length <= 1) $wire.press(event.key); }, 70); }
            if ($wire.step === 'pay' && event.key === 'Backspace') { $wire.backspace(); }
        },
    }"
    x-on:keydown.window="key($event)"
>
    <x-idle-sign-out hold="['pay', 'mpesa'].includes($wire.step)" />

    @if (in_array($step, ['pay', 'mpesa'], true))
        <div wire:poll.15s="keepAlive" class="hidden"></div>
    @endif

    <header class="flex items-center justify-between gap-4 border-b border-line px-4 py-2">
        <div class="min-w-0">
            <p class="truncate text-xs font-semibold tracking-widest text-primary uppercase">{{ $identity?->business_name }}</p>
            <p class="truncate text-sm text-muted">{{ $staff?->name }} · {{ $staff ? __("terminal.roles.{$staff->role}") : '' }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($tableService)
                <button type="button" wire:click="showTickets" @class(['key h-12 px-4 text-sm', 'border-primary text-primary' => $step === 'tickets']) @disabled(! in_array($step, ['cart', 'table', 'tickets'], true))>{{ __('terminal.tickets.open', ['count' => $openTicketCount]) }}</button>
            @endif
            @if ($staff?->can(\App\Models\StaffMember::TAKE_PAYMENTS))
                @if (in_array($step, ['pay', 'mpesa'], true))
                    <span class="key h-12 px-4 text-sm opacity-50" aria-disabled="true">{{ __('terminal.sales.title') }}</span>
                    <span class="key h-12 px-4 text-sm opacity-50" aria-disabled="true">{{ __('terminal.cash_up.title') }}</span>
                @else
                    <a href="{{ route('sales') }}" class="key h-12 px-4 text-sm">{{ __('terminal.sales.title') }}</a>
                    <a href="{{ route('cash-up') }}" @class(['key h-12 px-4 text-sm', 'border-primary text-primary' => ! $shiftOpen])>{{ __('terminal.cash_up.title') }}</a>
                @endif
            @endif
            <x-language-picker />
            @if (in_array($staff?->role, ['manager', 'owner'], true))
                <a href="{{ route('printer') }}" class="key h-12 px-4 text-sm">{{ __('terminal.printer.title') }}</a>
            @endif
            <a href="{{ route('pin') }}" class="key h-12 px-4 text-sm">{{ __('terminal.home.change_pin') }}</a>
            <button type="button" wire:click="signOut" class="key h-12 px-4 text-sm" @disabled(in_array($step, ['pay', 'mpesa'], true))>{{ __('terminal.home.switch') }}</button>
        </div>
    </header>

    <div class="grid min-h-0 flex-1 grid-cols-[1fr_24rem]">
        {{-- Menu --}}
        <section class="flex min-h-0 flex-col border-r border-line">
            <nav class="flex gap-2 overflow-x-auto border-b border-line p-2" aria-label="{{ __('terminal.sale.categories') }}">
                <button type="button" wire:click="pickCategory(null)" @class(['key h-12 shrink-0 px-4 text-base', 'border-primary text-primary' => $category === null])>{{ __('terminal.sale.all') }}</button>
                @foreach ($categories as $cat)
                    <button type="button" wire:key="cat-{{ $cat->id }}" wire:click="pickCategory('{{ $cat->id }}')" @class(['key h-12 shrink-0 px-4 text-base', 'border-primary text-primary' => $category === $cat->id])>{{ $cat->name }}</button>
                @endforeach
            </nav>

            <div class="grid min-h-0 flex-1 auto-rows-[6rem] grid-cols-[repeat(auto-fill,minmax(10rem,1fr))] content-start gap-2 overflow-y-auto p-2">
                @forelse ($items as $item)
                    <button
                        type="button"
                        wire:key="item-{{ $item->id }}"
                        wire:click="add('{{ $item->id }}')"
                        @disabled($step !== 'cart')
                        class="key h-24 flex-col items-start justify-between p-3 text-left"
                    >
                        <span class="line-clamp-2 text-base leading-tight">{{ $item->name }}</span>
                        <span class="font-mono text-sm text-muted">{{ \VendaPOS\Core\ValueObjects\Money::fromCents($item->price_cents)->format() }}</span>
                    </button>
                @empty
                    <p class="col-span-full p-6 text-muted">{{ __('terminal.sale.no_items') }}</p>
                @endforelse
            </div>
        </section>

        {{-- Cart and payment --}}
        <aside class="flex min-h-0 flex-col bg-panel">
            @if ($step === 'done' && $done)
                <div class="flex flex-1 flex-col items-center justify-center gap-6 p-6 text-center" role="status" @if ($printerReady) wire:poll.5s @endif>
                    <p class="text-sm tracking-widest text-ok uppercase">{{ __('terminal.sale.done.title', ['number' => $done['number']]) }}</p>
                    <p class="text-muted">{{ __('terminal.sale.done.total', ['amount' => \VendaPOS\Core\ValueObjects\Money::fromCents($done['total_cents'])->format()]) }}</p>
                    @if ($done['change_cents'] > 0)
                        <div>
                            <p class="text-lg">{{ __('terminal.sale.done.change') }}</p>
                            <p class="font-mono text-5xl font-bold">{{ \VendaPOS\Core\ValueObjects\Money::fromCents($done['change_cents'])->format() }}</p>
                        </div>
                    @else
                        <p class="text-2xl font-semibold">{{ __('terminal.sale.done.no_change') }}</p>
                    @endif
                    <button type="button" wire:click="newSale" class="key-primary h-16 w-full" autofocus>{{ __('terminal.sale.done.new') }}</button>
                    @if ($printerReady && $staff?->can(\App\Models\StaffMember::REPRINT_RECEIPTS))
                        <button type="button" wire:click="reprint" class="key h-12 w-full text-base">{{ __('terminal.sale.done.reprint') }}</button>
                    @endif
                    @if ($printError)
                        <p class="text-sm text-danger" role="alert">{{ __('terminal.sale.done.print_failed', ['reason' => $printError]) }}</p>
                    @endif
                </div>
            @elseif ($step === 'tickets')
                <div class="flex min-h-0 flex-1 flex-col" wire:poll.10s>
                    <div class="flex items-center justify-between border-b border-line px-3 py-2">
                        <p class="font-semibold">{{ __('terminal.tickets.title') }}</p>
                        <button type="button" wire:click="closeTicket" class="key h-12 px-4 text-sm">{{ __('terminal.sale.back') }}</button>
                    </div>
                    <ul class="min-h-0 flex-1 divide-y divide-line overflow-y-auto">
                        @forelse ($openTickets as $open)
                            <li wire:key="ticket-{{ $open->id }}">
                                <button type="button" wire:click="openTicket('{{ $open->id }}')" class="flex w-full items-center gap-3 px-3 py-3 text-left hover:bg-line active:bg-line disabled:opacity-40" @disabled($open->status === 'closed')>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-lg">{{ $open->title() }}</p>
                                        <p class="text-sm text-muted">
                                            {{ $open->opened_at->setTimezone(\App\Livewire\StatusBar::TIMEZONE)->format('H:i') }}
                                            @if ($open->status === 'bill_requested') · <span class="text-primary">{{ __('terminal.tickets.billed') }}</span>@endif
                                            @if ($open->sync_status === 'pending') · {{ __('terminal.tickets.not_sent') }}@endif
                                        </p>
                                        @if ($open->sync_status === 'rejected')
                                            <p class="text-sm text-danger">{{ __('terminal.tickets.rejected.'.($open->sync_reason === \App\Sync\SyncTickets::LINES_AFTER_PAYMENT ? 'lines_after_payment' : 'other')) }}</p>
                                        @endif
                                    </div>
                                    <p class="font-mono">{{ $open->totals()->total->format() }}</p>
                                </button>
                            </li>
                        @empty
                            <li class="p-6 text-muted">{{ __('terminal.tickets.none') }}</li>
                        @endforelse
                    </ul>
                </div>
            @elseif ($step === 'table')
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="flex items-center justify-between border-b border-line px-3 py-2">
                        <p class="font-semibold">{{ __('terminal.tickets.where') }}</p>
                        <button type="button" wire:click="closeTicket" class="key h-12 px-4 text-sm">{{ __('terminal.sale.back') }}</button>
                    </div>
                    <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
                        @foreach ($areas as $area)
                            <div wire:key="area-{{ $area->id }}">
                                <p class="mb-1 text-sm text-muted">{{ $area->name }}</p>
                                <div class="grid grid-cols-4 gap-1">
                                    @foreach ($area->tables()->orderBy('sort_order')->orderBy('name')->get() as $diningTable)
                                        <button type="button" wire:key="table-{{ $diningTable->id }}" wire:click="sendTo('{{ $diningTable->id }}')" class="key h-14 font-mono text-base">{{ $diningTable->name }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        <form wire:submit="sendTo" class="space-y-1 border-t border-line pt-3">
                            <p class="text-sm text-muted">{{ __('terminal.tickets.takeaway_help') }}</p>
                            <input type="text" wire:model="takeawayName" maxlength="40" autocomplete="off" placeholder="{{ __('terminal.tickets.takeaway_name') }}" class="h-12 w-full border border-line bg-surface px-3 text-white" aria-label="{{ __('terminal.tickets.takeaway_name') }}">
                            <button type="submit" class="key h-12 w-full text-base">{{ __('terminal.tickets.send_takeaway') }}</button>
                        </form>
                    </div>
                    @if ($message)
                        <p class="mx-3 mb-2 border border-danger px-3 py-2 text-sm text-danger" role="alert">{{ $message }}</p>
                    @endif
                </div>
            @else
                @if ($ticket)
                    <div class="flex items-center justify-between gap-2 border-b border-line px-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-lg font-semibold">{{ $ticket->title() }}</p>
                            @if ($ticket->status === 'bill_requested')<p class="text-sm text-primary">{{ __('terminal.tickets.billed') }}</p>@endif
                        </div>
                        @if ($step === 'cart')
                            <button type="button" wire:click="closeTicket" class="key size-12 text-xl" aria-label="{{ __('terminal.tickets.close') }}">×</button>
                        @endif
                    </div>
                @endif
                <ul class="min-h-0 flex-1 divide-y divide-line overflow-y-auto" aria-label="{{ __('terminal.sale.cart') }}">
                    {{-- Refused by the server: those items count in full again. --}}
                    @foreach ($ticket?->voids->where('sync_status', \App\Models\TicketLineVoid::REFUSED) ?? [] as $refusedVoid)
                        <li wire:key="refused-void-{{ $refusedVoid->id }}" class="px-3 py-2 text-sm text-danger" role="alert">
                            {{ __('terminal.tickets.void.not_taken_off', ['quantity' => $refusedVoid->quantity, 'item' => $ticket->lines->firstWhere('id', $refusedVoid->ticket_line_id)?->name ?? '—']) }}
                        </li>
                    @endforeach
                    {{-- Sent lines are locked: taking one off is a manager void, on its own screen. --}}
                    @php $canTakeOff = $step === 'cart' && $staff?->can(\App\Models\StaffMember::TAKE_ORDERS); @endphp
                    @if ($canTakeOff && $sent !== [])
                        <li class="px-3 pt-2 text-xs text-muted">{{ __('terminal.tickets.void.tap') }}</li>
                    @endif
                    @foreach ($sent as $sentLine)
                        @php $orderLine = $sentLine->toOrderLine(); @endphp
                        <li wire:key="sent-{{ $sentLine->id }}" class="text-muted">
                            <a @if ($canTakeOff) href="{{ route('tickets.take-off', ['ticketId' => $ticket->id, 'lineId' => $sentLine->id]) }}" @endif
                                @class(['flex items-center gap-2 px-3 py-2', 'hover:bg-panel' => $canTakeOff])>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate">{{ $sentLine->name }}</p>
                                    <p class="font-mono text-sm">
                                        {{ $orderLine->quantity }} × {{ \VendaPOS\Core\ValueObjects\Money::fromCents($sentLine->unit_price_cents)->format() }} · {{ __('terminal.tickets.sent_line') }}
                                        @if ($sentLine->voidedQuantity() > 0) · <span class="text-danger">{{ __('terminal.tickets.void.voided_count', ['count' => $sentLine->voidedQuantity()]) }}</span>@endif
                                    </p>
                                </div>
                                <p class="font-mono">{{ \VendaPOS\Core\ValueObjects\Money::fromCents($orderLine->line_total_cents)->format() }}</p>
                            </a>
                        </li>
                    @endforeach
                    @forelse ($lines as $line)
                        <li wire:key="line-{{ $line['item']->id }}" class="flex items-center gap-2 px-3 py-2">
                            <div class="min-w-0 flex-1">
                                <p class="truncate">{{ $line['item']->name }}</p>
                                <p class="font-mono text-sm text-muted">{{ $line['quantity'] }} × {{ $line['data']->unitPrice()->format() }}</p>
                            </div>
                            <p class="font-mono">{{ $line['data']->lineTotal()->format() }}</p>
                            @if ($step === 'cart')
                                <button type="button" wire:click="decrement('{{ $line['item']->id }}')" class="key size-12 text-2xl" aria-label="{{ __('terminal.sale.less') }}">−</button>
                                <button type="button" wire:click="add('{{ $line['item']->id }}')" class="key size-12 text-2xl" aria-label="{{ __('terminal.sale.more') }}">+</button>
                            @endif
                        </li>
                    @empty
                        @if ($sent === [])
                            <li class="p-6 text-muted">{{ __('terminal.sale.empty') }}</li>
                        @endif
                    @endforelse
                </ul>

                <dl class="space-y-1 border-t border-line px-3 py-2 text-sm">
                    <div class="flex justify-between text-muted"><dt>{{ __('terminal.sale.vat') }}</dt><dd class="font-mono">{{ $totals->vat->format() }}</dd></div>
                    @foreach ($mpesaPaid as $index => $paid)
                        <div wire:key="paid-{{ $index }}" class="flex items-center justify-between gap-2 text-ok">
                            <dt>{{ __(match (true) { $paid['method'] === 'mpesa_code' => 'terminal.sale.code.paid', ($paid['via'] ?? null) === 'qr' => 'terminal.sale.qr.paid', default => 'terminal.sale.mpesa.paid' }, ['reference' => $paid['reference'] ?? '']) }}</dt>
                            <dd class="flex items-center gap-2 font-mono">
                                − {{ \VendaPOS\Core\ValueObjects\Money::fromCents($paid['amount_cents'])->format() }}
                                @if ($paid['method'] === 'mpesa_code' && $step === 'pay')
                                    <button type="button" wire:click="removeTypedCode({{ $index }})" class="key size-12 text-base" aria-label="{{ __('terminal.sale.code.remove') }}">×</button>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                    <div class="flex justify-between text-2xl font-bold text-white"><dt>{{ $mpesaPaid === [] ? __('terminal.sale.total') : __('terminal.sale.to_pay') }}</dt><dd class="font-mono">{{ $remaining->format() }}</dd></div>
                </dl>

                @if ($message)
                    <p class="mx-3 mb-2 border border-danger px-3 py-2 text-sm text-danger" role="alert">{{ $message }}</p>
                @endif
                @if ($notice)
                    <p class="mx-3 mb-2 border border-ok px-3 py-2 text-sm text-ok" role="status">{{ $notice }}</p>
                @endif
                {{-- Cash-up: payments wait for a counted float. Orders can still go to the kitchen. --}}
                @if (! $shiftOpen && $step === 'cart' && $staff?->can(\App\Models\StaffMember::TAKE_PAYMENTS))
                    <a href="{{ route('cash-up') }}" class="mx-3 mb-2 block border border-primary px-3 py-2 text-sm text-primary">{{ __('terminal.cash_up.open_to_sell') }}</a>
                @endif

                @if ($step === 'cart' && $tableService)
                    <div class="grid grid-cols-2 gap-2 p-3">
                        <button type="button" wire:click="clearCart" class="key h-14 px-4 text-base" @disabled($lines === [])>{{ __('terminal.sale.clear') }}</button>
                        <button type="button" wire:click="sendOrder" class="key h-14 text-base" @disabled($lines === [] || ! $staff?->can(\App\Models\StaffMember::TAKE_ORDERS))>{{ $ticket ? __('terminal.tickets.send_more') : __('terminal.tickets.send') }}</button>
                        @if ($ticket)
                            <button type="button" wire:click="printBill" class="key h-14 text-base" @disabled(! $printerReady || $sent === [])>{{ __('terminal.tickets.print_bill') }}</button>
                        @endif
                        @if ($staff?->can(\App\Models\StaffMember::TAKE_PAYMENTS))
                            <button type="button" wire:click="startPayment" @class(['key-primary h-14', 'col-span-2' => ! $ticket]) @disabled(($lines === [] && $sent === []) || ! $shiftOpen)>{{ __('terminal.sale.pay') }}</button>
                        @endif
                    </div>
                @elseif ($step === 'cart')
                    <div class="grid grid-cols-[auto_1fr] gap-2 p-3">
                        <button type="button" wire:click="clearCart" class="key h-16 px-4 text-base" @disabled($lines === [])>{{ __('terminal.sale.clear') }}</button>
                        <button type="button" wire:click="startPayment" class="key-primary h-16" @disabled($lines === [] || ! $shiftOpen)>{{ __('terminal.sale.pay') }}</button>
                    </div>
                @elseif ($step === 'pay')
                    @if ($waitingOnBillQr)
                        <p class="mx-3 mb-2 border border-line px-3 py-2 text-sm text-muted" wire:poll.3s="checkBillQr" role="status">{{ __('terminal.sale.qr.waiting') }}</p>
                    @endif
                    <div class="max-h-[70%] shrink-0 space-y-2 overflow-y-auto border-t border-line p-3">
                        @if ($mpesaPaid === [])
                            <button type="button" wire:click="backToCart" class="key h-12 w-full text-base">← {{ __('terminal.sale.back_to_order') }}</button>
                        @endif
                        <p class="text-sm text-muted">{{ __('terminal.sale.cash') }}</p>
                        <div class="flex items-center justify-between border border-line bg-surface px-3 py-2">
                            <span class="text-muted">{{ __('terminal.sale.given') }}</span>
                            <span class="font-mono text-2xl">KES {{ $cashGiven === '' ? '0' : number_format((int) $cashGiven) }}</span>
                        </div>
                        <div class="grid grid-cols-4 gap-1">
                            @foreach ($quickCash as $amount)
                                <button type="button" wire:click="quickCash({{ $amount }})" class="key h-12 font-mono text-base">{{ number_format($amount) }}</button>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-3 gap-1">
                            @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $digit)
                                <button type="button" wire:click="press('{{ $digit }}')" class="key h-12 font-mono">{{ $digit }}</button>
                            @endforeach
                            <button type="button" wire:click="backToCart" class="key h-12 text-sm">{{ __('terminal.sale.back') }}</button>
                            <button type="button" wire:click="press('0')" class="key h-12 font-mono">0</button>
                            <button type="button" wire:click="backspace" class="key h-12 text-sm">{{ __('terminal.pair.backspace') }}</button>
                        </div>
                        <button type="button" wire:click="complete" class="key-primary h-14 w-full">{{ __('terminal.sale.complete') }}</button>

                        <form wire:submit="requestMpesa" class="space-y-1 border-t border-line pt-2">
                            <p class="text-sm text-muted">{{ __('terminal.sale.mpesa.title') }} @unless ($online)<span class="text-danger">· {{ __('terminal.sale.mpesa.needs_internet') }}</span>@endunless</p>
                            <div class="grid grid-cols-[1fr_7rem] gap-1">
                                <input type="tel" wire:model="phone" inputmode="tel" autocomplete="off" placeholder="07XX XXX XXX" class="h-12 border border-line bg-surface px-3 font-mono text-white" aria-label="{{ __('terminal.sale.mpesa.phone') }}">
                                <input type="text" wire:model="mpesaAmount" inputmode="numeric" class="h-12 border border-line bg-surface px-3 font-mono text-white" aria-label="{{ __('terminal.sale.mpesa.amount') }}">
                            </div>
                            <button type="submit" class="key h-12 w-full text-base" @disabled(! $online)>{{ __('terminal.sale.mpesa.send') }}</button>
                        </form>

                        {{-- Paid straight to the paybill or till: works offline, confirmed later on the server. --}}
                        <form wire:submit="addTypedCode" class="space-y-1 border-t border-line pt-2">
                            <p class="text-sm text-muted">{{ __('terminal.sale.code.title') }}</p>
                            <div class="grid grid-cols-[1fr_7rem] gap-1">
                                <input type="text" wire:model="typedCode" maxlength="12" autocomplete="off" autocapitalize="characters" placeholder="TJL7QW2E9R" class="h-12 border border-line bg-surface px-3 font-mono text-white uppercase" aria-label="{{ __('terminal.sale.code.code') }}">
                                <input type="text" wire:model="codeAmount" inputmode="numeric" class="h-12 border border-line bg-surface px-3 font-mono text-white" aria-label="{{ __('terminal.sale.mpesa.amount') }}">
                            </div>
                            <button type="submit" class="key h-12 w-full text-base">{{ __('terminal.sale.code.add') }}</button>
                        </form>
                    </div>
                @elseif ($step === 'mpesa')
                    <div class="space-y-3 border-t border-line p-4 text-center" wire:poll.2s="checkMpesa" role="status">
                        <p class="text-lg font-semibold">{{ __('terminal.sale.mpesa.waiting') }}</p>
                        <p class="font-mono text-3xl">{{ \VendaPOS\Core\ValueObjects\Money::fromCents($waiting['amount_cents'] ?? 0)->format() }}</p>
                        @if (isset($waiting['phone']))
                            <p class="font-mono text-base">{{ __('terminal.sale.mpesa.sent_to', ['phone' => \App\Livewire\Sale::maskedPhone($waiting['phone'])]) }}</p>
                        @endif
                        <p class="text-sm text-muted">{{ __('terminal.sale.mpesa.waiting_help') }}</p>
                        @if ($waitedLongEnough)
                            <p class="text-sm text-danger">{{ __('terminal.sale.mpesa.slow') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" wire:click="sendAgain" class="key h-12 text-base" @disabled(! $online)>{{ __('terminal.sale.mpesa.send_again') }}</button>
                                <button type="button" wire:click="stopWaiting" class="key h-12 text-base">{{ __('terminal.sale.mpesa.stop') }}</button>
                            </div>
                        @endif
                    </div>
                @endif
            @endif
        </aside>
    </div>
</div>
