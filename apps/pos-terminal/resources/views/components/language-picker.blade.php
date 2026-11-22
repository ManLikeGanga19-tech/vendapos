<div class="flex">
    @foreach (\App\Http\Middleware\UseTillLocale::LOCALES as $locale)
        <button
            type="button"
            wire:click="useLanguage('{{ $locale }}')"
            @class(['key px-4 text-base', 'bg-line' => app()->getLocale() === $locale])
            aria-pressed="{{ app()->getLocale() === $locale ? 'true' : 'false' }}"
        >{{ __("terminal.language.{$locale}") }}</button>
    @endforeach
</div>
