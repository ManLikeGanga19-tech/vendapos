@php
    /** @var \App\Domain\CashUp\Models\Shift $shift */
    $row = 'display:flex;justify-content:space-between;gap:1rem';
    $rule = 'border-top:1px dashed currentColor;opacity:.4;margin:.5rem 0';
@endphp
{{-- Laid out like the printed Z report, so an owner can hold the paper up to it. --}}
<div style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.875rem;line-height:1.6">
    <div style="{{ $row }}"><span>{{ __('owner.cash_ups.branch') }}</span><span>{{ $branch }}</span></div>
    <div style="{{ $row }}"><span>{{ __('owner.cash_ups.till') }}</span><span>{{ $till }}</span></div>
    <div style="{{ $row }}"><span>{{ __('owner.cash_ups.opened') }}</span><span>{{ $opened }}</span></div>
    <div style="{{ $row }}"><span>{{ __('owner.cash_ups.closed') }}</span><span>{{ $closed ?? __('owner.cash_ups.still_open') }}</span></div>

    <div style="{{ $rule }}"></div>
    <div style="{{ $row }}"><span>{{ __('owner.cash_ups.float') }}</span><span>{{ $float }}</span></div>
    @if ($closed !== null)
        <div style="{{ $row }}"><span>+ {{ __('owner.cash_ups.cash_taken') }}</span><span>{{ $cashTaken }}</span></div>
        <div style="{{ $row }}"><span>− {{ __('owner.cash_ups.pay_outs') }}</span><span>{{ $payOuts }}</span></div>
        <div style="{{ $row }}"><span>− {{ __('owner.cash_ups.drops') }}</span><span>{{ $drops }}</span></div>
        <div style="{{ $row }}"><span>− {{ __('owner.cash_ups.void_refunds') }}</span><span>{{ $voidRefunds }}</span></div>
        <div style="{{ $row }};font-weight:700"><span>{{ __('owner.cash_ups.expected') }}</span><span>{{ $expected }}</span></div>
        <div style="{{ $row }}"><span>{{ __('owner.cash_ups.counted') }}</span><span>{{ $counted }}</span></div>
        <div style="{{ $row }};font-weight:700"><span>{{ __('owner.cash_ups.variance') }}</span><span>{{ $variance }}</span></div>
        @if ($tillExpected !== null)
            <div style="font-family:system-ui,sans-serif;font-weight:600">{{ __('owner.cash_ups.till_expected', ['amount' => $tillExpected]) }}</div>
        @endif
        @if ($approvedBy !== null)
            <div style="{{ $row }}"><span>{{ __('owner.cash_ups.approved_by') }}</span><span>{{ $approvedBy }}</span></div>
            <div style="font-family:system-ui,sans-serif">{{ $shift->note }}</div>
        @endif
    @endif

    @foreach ([__('owner.cash_ups.opening_count') => $openingCounts, __('owner.cash_ups.closing_count') => $closingCounts] as $heading => $counts)
        @if ($counts !== [])
            <div style="{{ $rule }}"></div>
            <strong style="font-family:system-ui,sans-serif">{{ $heading }}</strong>
            @foreach ($counts as $count)
                <div style="{{ $row }}"><span>{{ $count['count'] }} × {{ $count['label'] }}</span><span>{{ $count['total'] }}</span></div>
            @endforeach
        @endif
    @endforeach

    <div style="{{ $rule }}"></div>
    <strong style="font-family:system-ui,sans-serif">{{ __('owner.cash_ups.movements') }}</strong>
    @forelse ($movements as $movement)
        <div style="{{ $row }}"><span>{{ $movement['when'] }} {{ $movement['kind'] }} · {{ $movement['who'] }}</span><span>{{ $movement['amount'] }}</span></div>
        @if ($movement['reason'] !== null)
            <div style="font-family:system-ui,sans-serif;opacity:.8">{{ $movement['reason'] }}</div>
        @endif
    @empty
        <div style="font-family:system-ui,sans-serif;opacity:.7">{{ __('owner.cash_ups.no_movements') }}</div>
    @endforelse
</div>
