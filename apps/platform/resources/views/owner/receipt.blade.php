@php
    use App\Domain\Orders\Models\OrderLine;
    use VendaPOS\Core\ValueObjects\Money;

    /** @var \App\Domain\Orders\Models\Order $order */
    $row = 'display:flex;justify-content:space-between;gap:1rem';
    $rule = 'border-top:1px dashed currentColor;opacity:.4;margin:.5rem 0';
@endphp
{{-- Laid out like the printed slip, so an owner can hold the paper up to it. --}}
<div style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.875rem;line-height:1.6">
    <div style="{{ $row }}"><span>{{ __('owner.receipts.number') }}</span><span>{{ $order->receipt_number ?? '#'.$order->sequence_number }}</span></div>
    <div style="{{ $row }}"><span>{{ __('owner.receipts.time') }}</span><span>{{ $time }}</span></div>
    <div style="{{ $row }}"><span>{{ __('owner.receipts.branch') }}</span><span>{{ $branch }}</span></div>
    <div style="{{ $row }}"><span>{{ __('owner.receipts.till') }}</span><span>{{ $till }}</span></div>
    @if ($staff !== null)
        <div style="{{ $row }}"><span>{{ __('owner.receipts.staff') }}</span><span>{{ $staff }}</span></div>
    @endif
    @if ($table !== null)
        <div style="{{ $row }}"><span>{{ __('owner.receipts.served_at') }}</span><span>{{ $table }}</span></div>
    @endif
    <div style="{{ $row }}"><span>{{ __('owner.receipts.status') }}</span><span>{{ __('owner.receipts.statuses.'.$order->status->value) }}</span></div>

    <div style="{{ $rule }}"></div>
    @foreach ($order->lines as $line)
        @php /** @var OrderLine $line */ @endphp
        <div style="{{ $row }}"><span>{{ $line->quantity }} x {{ $line->name }}</span><span>{{ Money::fromCents($line->line_total_cents)->format() }}</span></div>
    @endforeach
    <div style="{{ $rule }}"></div>

    <div style="{{ $row }}"><span>{{ __('owner.receipts.net') }}</span><span>{{ $net }}</span></div>
    @foreach ($bands as $band)
        <div style="{{ $row }}"><span>{{ $band['label'] }}</span><span>{{ $band['vat'] }}</span></div>
    @endforeach
    <div style="{{ $row }};font-weight:700"><span>{{ __('owner.receipts.total') }}</span><span>{{ Money::fromCents($order->total_cents)->format() }}</span></div>
    <div style="{{ $row }}"><span>{{ __('owner.receipts.paid_by') }}</span><span>{{ $paidBy !== '' ? $paidBy : '—' }}</span></div>

    <div style="{{ $rule }}"></div>
    <div style="font-family:system-ui,sans-serif">
        <strong>{{ __('owner.receipts.reprints') }}</strong>
        @forelse ($reprints as $reprint)
            <div>{{ $reprint }}</div>
        @empty
            <div style="opacity:.7">{{ __('owner.receipts.never_reprinted') }}</div>
        @endforelse
    </div>
</div>
