@php
    use App\Livewire\CashUp;
    use App\Livewire\StatusBar;
    use App\Models\StaffMember;
    use VendaPOS\Core\ValueObjects\Money;

    $money = fn (int $cents): string => Money::fromCents($cents)->format();
    $time = fn ($moment): string => $moment->setTimezone(StatusBar::TIMEZONE)->format('d/m H:i');
    $differenceWord = fn (int $cents): string => match (true) { $cents < 0 => 'short', $cents > 0 => 'over', default => 'exact' };
@endphp
<div class="mx-auto flex min-h-[calc(100vh-2.4rem)] max-w-5xl flex-col gap-4 px-6 py-6">
    {{-- Same steps as CashUp::COUNTING, plus a manager entering their PIN. --}}
    <x-idle-sign-out hold="['open', 'count', 'pin'].includes($wire.step)" />
    @if (in_array($step, CashUp::COUNTING, true))
        {{-- Counting a drawer takes longer than the idle limit: stay signed in while on this step. --}}
        <div wire:poll.15s="keepAlive" class="hidden"></div>
    @endif

    <header class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">{{ __('terminal.cash_up.title') }}</h1>
            <p class="mt-1 text-muted">
                @if ($shift)
                    {{ __('terminal.cash_up.open_since', ['shift' => $shiftLabel, 'time' => $time($shift->opened_at), 'name' => $openedBy ?? '—']) }}
                @else
                    {{ __('terminal.cash_up.no_shift') }}
                @endif
            </p>
        </div>
        @if (! in_array($step, ['difference', 'closed'], true))
            <button type="button" wire:click="back" class="key h-12 px-4 text-base">{{ __('terminal.sale.back') }}</button>
        @endif
    </header>

    @if ($message)
        <p class="border border-danger px-4 py-3 text-danger" role="alert">{{ $message }}</p>
    @endif
    @if ($notice)
        <p class="border border-ok px-4 py-3 text-ok" role="status">{{ $notice }}</p>
    @endif

    @if (in_array($step, ['open', 'count'], true))
        <section class="flex flex-col gap-3">
            <p class="text-lg">{{ $step === 'open' ? __('terminal.cash_up.count_float') : __('terminal.cash_up.count_close') }}</p>
            @if ($step === 'count')
                <p class="text-sm text-muted">{{ $recounted ? __('terminal.cash_up.recount_help') : __('terminal.cash_up.blind_help') }}</p>
            @endif
            <form wire:submit="{{ $step === 'open' ? 'openShift' : 'countDone' }}" class="flex flex-col gap-3">
                <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                    @foreach ($denominations as $denomination)
                        <label wire:key="count-{{ $denomination->value }}" class="flex items-center gap-3">
                            <span class="w-28 font-mono">KES {{ number_format($denomination->value) }}</span>
                            <span class="w-14 text-sm text-muted">{{ $denomination->isNote() ? __('terminal.cash_up.notes') : __('terminal.cash_up.coins') }}</span>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="5" autocomplete="off"
                                wire:model.live.debounce.300ms="counts.{{ $denomination->value }}"
                                aria-label="{{ __('terminal.cash_up.how_many', ['value' => 'KES '.number_format($denomination->value)]) }}"
                                class="h-12 w-24 border border-line bg-panel px-3 text-right font-mono text-white">
                        </label>
                    @endforeach
                </div>
                <div class="flex items-center justify-between border-t border-line pt-3">
                    <span class="text-lg">{{ __('terminal.cash_up.counted_total') }}</span>
                    <span class="font-mono text-3xl font-bold">{{ $typedTotal->format() }}</span>
                </div>
                <button type="submit" class="key-primary h-16">{{ $step === 'open' ? __('terminal.cash_up.open') : __('terminal.cash_up.count_done') }}</button>
            </form>
        </section>
    @elseif ($step === 'shift' && $shift)
        <section class="grid grid-cols-2 gap-3">
            <button type="button" wire:click="startOut('pay_out')" class="key h-24 flex-col items-start justify-center px-5 text-left">
                <span class="text-xl">{{ __('terminal.cash_up.kinds.pay_out') }}</span>
                <span class="text-sm font-normal text-muted">{{ __('terminal.cash_up.pay_out_help') }}</span>
            </button>
            <button type="button" wire:click="startOut('drop')" class="key h-24 flex-col items-start justify-center px-5 text-left">
                <span class="text-xl">{{ __('terminal.cash_up.kinds.drop') }}</span>
                <span class="text-sm font-normal text-muted">{{ __('terminal.cash_up.drop_help') }}</span>
            </button>
            @if ($staff?->can(StaffMember::CLOSE_SHIFTS))
                <button type="button" wire:click="showX" class="key h-24 flex-col items-start justify-center px-5 text-left">
                    <span class="text-xl">{{ __('terminal.cash_up.x_report') }}</span>
                    <span class="text-sm font-normal text-muted">{{ __('terminal.cash_up.x_help') }}</span>
                </button>
            @endif
            <button type="button" wire:click="startClose" class="key h-24 flex-col items-start justify-center border-primary px-5 text-left text-primary">
                <span class="text-xl">{{ __('terminal.cash_up.close') }}</span>
                <span class="text-sm font-normal text-muted">{{ __('terminal.cash_up.close_help') }}</span>
            </button>
        </section>
    @elseif ($step === 'out')
        <form wire:submit="confirmOut" class="flex max-w-xl flex-col gap-3">
            <p class="text-lg">{{ __("terminal.cash_up.out_title.{$outKind}") }}</p>
            <label class="flex flex-col gap-2">
                <span class="text-sm text-muted">{{ __('terminal.cash_up.amount') }}</span>
                <input type="text" inputmode="numeric" pattern="[0-9]*" wire:model="amount" autocomplete="off" autofocus class="h-14 border border-line bg-panel px-3 font-mono text-2xl text-white">
            </label>
            @error('amount') <p class="text-sm text-danger">{{ $message }}</p> @enderror
            @if ($outKind === 'pay_out')
                <label class="flex flex-col gap-2">
                    <span class="text-sm text-muted">{{ __('terminal.cash_up.reason') }}</span>
                    <input type="text" wire:model="reason" maxlength="{{ \VendaPOS\Core\Data\CashMovementData::REASON_MAX }}" autocomplete="off" placeholder="{{ __('terminal.cash_up.reason_example') }}" class="h-12 border border-line bg-panel px-3 text-white">
                </label>
                @error('reason') <p class="text-sm text-danger">{{ $message }}</p> @enderror
            @endif
            <button type="submit" class="key-primary h-14">{{ __('terminal.sales.next') }}</button>
        </form>
    @elseif ($step === 'difference' && $result)
        <section class="flex flex-col items-center gap-4 py-6 text-center" role="status">
            <p class="text-lg">{{ __('terminal.cash_up.difference.'.$differenceWord($result['variance_cents'])) }}</p>
            <p class="font-mono text-5xl font-bold text-danger">{{ $money(abs($result['variance_cents'])) }}</p>
            <p class="text-muted">{{ __('terminal.cash_up.expected_counted', ['expected' => $money($result['expected_cents']), 'counted' => $money($result['counted_cents'])]) }}</p>
            <p class="max-w-xl text-muted">{{ $recounted ? __('terminal.cash_up.over_allowance_after_recount') : __('terminal.cash_up.over_allowance') }}</p>
            <div class="flex w-full max-w-md flex-col gap-2">
                @unless ($recounted)
                    <button type="button" wire:click="recount" class="key-primary h-16">{{ __('terminal.cash_up.recount') }}</button>
                @endunless
                <button type="button" wire:click="askApproval" @class(['h-14 text-base', 'key-primary' => $recounted, 'key' => ! $recounted])>{{ __('terminal.cash_up.get_manager') }}</button>
            </div>
        </section>
    @elseif ($step === 'note')
        <form wire:submit="confirmNote" class="flex max-w-xl flex-col gap-3">
            <label class="flex flex-col gap-2">
                <span class="text-lg">{{ __('terminal.cash_up.note') }}</span>
                <input type="text" wire:model="note" maxlength="{{ \VendaPOS\Core\Data\ShiftData::NOTE_MAX }}" autocomplete="off" autofocus placeholder="{{ __('terminal.cash_up.note_example') }}" class="h-12 border border-line bg-panel px-3 text-white">
            </label>
            @error('note') <p class="text-sm text-danger">{{ $message }}</p> @enderror
            <button type="submit" class="key-primary h-14">{{ __('terminal.sales.next') }}</button>
        </form>
    @elseif ($step === 'approver')
        <section class="flex flex-col gap-3">
            <p class="text-lg">{{ $purpose === 'close' ? __('terminal.cash_up.who_approves_close') : __('terminal.cash_up.who_approves_out') }}</p>
            @if ($approvers->isEmpty())
                <p class="text-muted">{{ __('terminal.cash_up.no_approvers') }}</p>
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
        <p class="text-center text-lg">
            {{ $purpose === 'close'
                ? __('terminal.cash_up.approve_close', ['name' => $approver->name])
                : __("terminal.cash_up.approve_out.{$outKind}", ['name' => $approver->name, 'amount' => $money((int) $amount * 100)]) }}
        </p>
        <x-pin-pad :length="$approver->pin_length" :filled="strlen($pin)" />
    @elseif (in_array($step, ['x', 'closed'], true) && $report)
        <section class="flex flex-col gap-4 border border-line bg-panel p-5">
            <div class="flex items-baseline justify-between gap-4">
                <p class="font-mono text-2xl font-bold">{{ $report->final ? __('terminal.shift_report.z') : __('terminal.shift_report.x') }} · {{ $report->number }}</p>
                <p class="text-muted">{{ $time($report->openedAt) }}@if ($report->closedAt) – {{ $time($report->closedAt) }}@endif</p>
            </div>
            <dl class="flex flex-col gap-1 font-mono">
                @foreach ([
                    'float' => $report->float,
                    'cash_taken' => $report->cashTaken,
                    'pay_outs' => $report->payOuts->multiply(-1),
                    'drops' => $report->drops->multiply(-1),
                    'void_refunds' => $report->voidRefunds->multiply(-1),
                ] as $key => $amount)
                    <div class="flex justify-between gap-4"><dt>{{ __("terminal.shift_report.{$key}") }}</dt><dd>{{ $amount->format() }}</dd></div>
                @endforeach
                <div class="flex justify-between gap-4 border-t border-line pt-2 font-bold"><dt>{{ __('terminal.shift_report.expected') }}</dt><dd>{{ $report->expected->format() }}</dd></div>
                @if ($report->counted && $report->variance)
                    <div class="flex justify-between gap-4"><dt>{{ __('terminal.shift_report.counted') }}</dt><dd>{{ $report->counted->format() }}</dd></div>
                    <div @class(['flex justify-between gap-4 text-xl font-bold', 'text-danger' => $report->variance->cents < 0, 'text-ok' => $report->variance->cents >= 0])>
                        <dt>{{ __('terminal.shift_report.'.$differenceWord($report->variance->cents)) }}</dt><dd>{{ $money(abs($report->variance->cents)) }}</dd>
                    </div>
                @endif
                <div class="mt-2 flex justify-between gap-4 border-t border-line pt-2 text-muted"><dt>{{ __('terminal.shift_report.mpesa') }}</dt><dd>{{ $report->mpesaTaken->format() }}</dd></div>
                <div class="flex justify-between gap-4 text-muted"><dt>{{ __('terminal.shift_report.sales') }}</dt><dd>{{ $report->sales }} · {{ __('terminal.shift_report.voided') }} {{ $report->voided }}</dd></div>
            </dl>
            @if ($report->approvedBy)
                <p class="text-muted">{{ __('terminal.shift_report.approved_by', ['name' => $report->approvedBy]) }} · {{ $report->note }}</p>
            @endif
        </section>
        <div class="flex flex-col gap-2 sm:flex-row">
            @if ($printerReady)
                <button type="button" wire:click="printReport" class="key h-14 flex-1 text-base">{{ $report->final ? __('terminal.cash_up.print_z') : __('terminal.cash_up.print_x') }}</button>
            @endif
            @if ($step === 'closed')
                <button type="button" wire:click="openNext" class="key-primary h-14 flex-1">{{ __('terminal.cash_up.open_next') }}</button>
                <a href="{{ route('home') }}" class="key h-14 flex-1 text-base">{{ __('terminal.cash_up.done') }}</a>
            @endif
        </div>
    @endif
</div>
