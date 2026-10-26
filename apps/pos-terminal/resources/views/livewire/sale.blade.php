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
            <x-language-picker />
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
                <div class="flex flex-1 flex-col items-center justify-center gap-6 p-6 text-center" role="status">
                    <p class="text-sm tracking-widest text-ok uppercase">{{ __('terminal.sale.done.title', ['number' => $done['sequence']]) }}</p>
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
                </div>
            @else
                <ul class="min-h-0 flex-1 divide-y divide-line overflow-y-auto" aria-label="{{ __('terminal.sale.cart') }}">
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
                        <li class="p-6 text-muted">{{ __('terminal.sale.empty') }}</li>
                    @endforelse
                </ul>

                <dl class="space-y-1 border-t border-line px-3 py-2 text-sm">
                    <div class="flex justify-between text-muted"><dt>{{ __('terminal.sale.vat') }}</dt><dd class="font-mono">{{ $totals->vat->format() }}</dd></div>
                    @foreach ($mpesaPaid as $paid)
                        <div class="flex justify-between text-ok"><dt>{{ __('terminal.sale.mpesa.paid', ['reference' => $paid['reference'] ?? '']) }}</dt><dd class="font-mono">− {{ \VendaPOS\Core\ValueObjects\Money::fromCents($paid['amount_cents'])->format() }}</dd></div>
                    @endforeach
                    <div class="flex justify-between text-2xl font-bold text-white"><dt>{{ $mpesaPaid === [] ? __('terminal.sale.total') : __('terminal.sale.to_pay') }}</dt><dd class="font-mono">{{ $remaining->format() }}</dd></div>
                </dl>

                @if ($message)
                    <p class="mx-3 mb-2 border border-danger px-3 py-2 text-sm text-danger" role="alert">{{ $message }}</p>
                @endif

                @if ($step === 'cart')
                    <div class="grid grid-cols-[auto_1fr] gap-2 p-3">
                        <button type="button" wire:click="clearCart" class="key h-16 px-4 text-base" @disabled($lines === [])>{{ __('terminal.sale.clear') }}</button>
                        <button type="button" wire:click="startPayment" class="key-primary h-16" @disabled($lines === [])>{{ __('terminal.sale.pay') }}</button>
                    </div>
                @elseif ($step === 'pay')
                    <div class="space-y-2 border-t border-line p-3">
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
                    </div>
                @elseif ($step === 'mpesa')
                    <div class="space-y-3 border-t border-line p-4 text-center" wire:poll.2s="checkMpesa" role="status">
                        <p class="text-lg font-semibold">{{ __('terminal.sale.mpesa.waiting') }}</p>
                        <p class="font-mono text-3xl">{{ \VendaPOS\Core\ValueObjects\Money::fromCents($waiting['amount_cents'] ?? 0)->format() }}</p>
                        <p class="text-sm text-muted">{{ __('terminal.sale.mpesa.waiting_help') }}</p>
                        @if ($waitedLongEnough)
                            <p class="text-sm text-danger">{{ __('terminal.sale.mpesa.slow') }}</p>
                            <button type="button" wire:click="stopWaiting" class="key h-12 w-full text-base">{{ __('terminal.sale.mpesa.stop') }}</button>
                        @endif
                    </div>
                @endif
            @endif
        </aside>
    </div>
</div>
