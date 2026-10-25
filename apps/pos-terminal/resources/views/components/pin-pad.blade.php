@props(['length', 'filled', 'message' => null])

{{-- Calls press(digit), backspace() and back() on the Livewire component. Keyboard digits work too; Escape goes back. --}}
<div
    class="mx-auto flex w-full max-w-md flex-col gap-6"
    x-data
    x-on:keydown.window="
        if ($event.ctrlKey || $event.altKey || $event.metaKey) return;
        if (/^[0-9]$/.test($event.key)) { $wire.press($event.key); }
        else if ($event.key === 'Backspace') { $event.preventDefault(); $wire.backspace(); }
        else if ($event.key === 'Escape') { $wire.back(); }
    "
>
    <div class="flex justify-center gap-4" aria-label="{{ __('terminal.sign_in.enter_pin') }}">
        @for ($i = 0; $i < $length; $i++)
            <span @class([
                'inline-block size-5 border-2',
                'border-primary bg-primary' => $i < $filled,
                'border-line' => $i >= $filled,
            ])></span>
        @endfor
    </div>

    @if ($message)
        <p class="border border-danger px-4 py-3 text-center text-danger" role="alert">{{ $message }}</p>
    @endif

    <div class="grid grid-cols-3 gap-2" wire:loading.class="opacity-60" wire:target="press">
        @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $digit)
            <button type="button" wire:click="press('{{ $digit }}')" class="key h-20 font-mono text-3xl">{{ $digit }}</button>
        @endforeach
        <button type="button" wire:click="back" class="key h-20 text-base">{{ __('terminal.sign_in.back') }}</button>
        <button type="button" wire:click="press('0')" class="key h-20 font-mono text-3xl">0</button>
        <button type="button" wire:click="backspace" class="key h-20 text-base">{{ __('terminal.pair.backspace') }}</button>
    </div>
</div>
