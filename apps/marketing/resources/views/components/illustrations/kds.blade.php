{{-- Kitchen display illustration (product and restaurant pages). Copy lives in lang/*/product.php under kds. --}}
<figure role="img" aria-label="{{ __('product.kds.label') }}" class="border border-line bg-surface p-5">
    <p class="flex items-center gap-2 text-sm font-semibold"><x-icon name="chef-hat" class="size-4 text-coral" />{{ __('product.kds.station') }}</p>
    <ul class="mt-4 grid gap-3 sm:grid-cols-3">
        @foreach (__('product.kds.tickets') as $ticket => $item)
            <li class="flex flex-col border border-line bg-canvas p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold">{{ $item['table'] }}</p>
                    <span @class(['tabular text-xs font-semibold', 'text-danger' => $ticket === 'one', 'text-pending' => $ticket === 'two', 'text-success' => $ticket === 'three'])>
                        {{ ['one' => '09:12', 'two' => '05:40', 'three' => '01:05'][$ticket] }}
                    </span>
                </div>
                <p class="mt-2 flex-1 text-xs text-muted">{{ $item['items'] }}</p>
                <span class="mt-3 border border-line py-1.5 text-center text-xs font-semibold text-ink">{{ __('product.kds.bump') }}</span>
            </li>
        @endforeach
    </ul>
</figure>
