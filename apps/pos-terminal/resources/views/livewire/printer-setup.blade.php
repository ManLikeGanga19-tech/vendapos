<div class="mx-auto flex max-w-xl flex-col gap-6 px-6 py-8">
    <x-idle-sign-out />

    <header class="flex items-center justify-between gap-4">
        <h1 class="text-3xl font-bold">{{ __('terminal.printer.title') }}</h1>
        <a href="{{ route('home') }}" class="key h-12 px-4 text-base">{{ __('terminal.sale.back') }}</a>
    </header>

    <p class="text-muted">{{ __('terminal.printer.help') }}</p>

    <form wire:submit="save" class="flex flex-col gap-4">
        <fieldset class="flex flex-col gap-2">
            <legend class="mb-2 text-sm text-muted">{{ __('terminal.printer.connection') }}</legend>
            @foreach ($connections as $option)
                <label @class(['key h-14 justify-start gap-3 px-4 text-base', 'border-primary' => $connection === $option])>
                    <input type="radio" wire:model.live="connection" value="{{ $option }}" class="size-5 accent-primary">
                    {{ __("terminal.printer.connections.{$option}") }}
                </label>
            @endforeach
        </fieldset>

        @if ($connection !== 'none')
            <label class="flex flex-col gap-2">
                <span class="text-sm text-muted">{{ __('terminal.printer.target') }}</span>
                <input type="text" wire:model="target" autocomplete="off" placeholder="{{ __("terminal.printer.examples.{$connection}") }}" class="h-12 border border-line bg-panel px-3 font-mono text-white">
                <span class="text-sm text-muted">{{ __("terminal.printer.target_help.{$connection}") }}</span>
                @error('target') <span class="text-sm text-danger">{{ $message }}</span> @enderror
            </label>

            <fieldset class="flex gap-2">
                <legend class="mb-2 text-sm text-muted">{{ __('terminal.printer.paper') }}</legend>
                @foreach ([80, 58] as $mm)
                    <label @class(['key h-12 flex-1 gap-2 text-base', 'border-primary' => $paperMm === $mm])>
                        <input type="radio" wire:model.live="paperMm" value="{{ $mm }}" class="size-5 accent-primary"> {{ $mm }} mm
                    </label>
                @endforeach
            </fieldset>
        @endif

        @if ($message)
            <p @class(['border px-4 py-3', 'border-ok text-ok' => $ok, 'border-danger text-danger' => ! $ok]) role="status">{{ $message }}</p>
        @endif

        <div class="grid grid-cols-2 gap-2">
            <button type="submit" class="key h-14 text-base">{{ __('terminal.printer.save') }}</button>
            <button type="button" wire:click="testPrint" class="key-primary h-14" @disabled($connection === 'none')>{{ __('terminal.printer.test') }}</button>
        </div>
    </form>
</div>
