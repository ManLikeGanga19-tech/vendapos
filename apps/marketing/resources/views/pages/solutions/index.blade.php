<x-layouts.site :title="__('solutions.index.meta.title')" :description="__('solutions.index.meta.description')">

    <section class="py-12 lg:py-20">
        <div class="container-page">
            <div class="max-w-3xl">
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-coral">{{ __('solutions.index.eyebrow') }}</p>
                <h1 class="text-display font-bold text-balance lg:text-display-lg">{{ __('solutions.index.title') }}</h1>
                <p class="mt-6 text-lg text-muted text-pretty">{{ __('solutions.index.lead') }}</p>
            </div>

            <ul class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (array_keys(__('home.verticals.items')) as $key)
                    <li><x-solution-card :key="$key" heading-level="h2" /></li>
                @endforeach
            </ul>

            <div class="reveal mt-12 flex flex-col gap-6 border border-line bg-surface p-6 sm:flex-row sm:items-center sm:justify-between lg:p-8">
                <div class="flex gap-4">
                    <x-feature-icon icon="building-2" />
                    <div>
                        <h2 class="text-lg font-semibold">{{ __('solutions.index.other_title') }}</h2>
                        <p class="mt-1 text-muted">{{ __('solutions.index.other_body') }}</p>
                    </div>
                </div>
                <x-button :href="route('demo')" icon="arrow-right" class="shrink-0">{{ __('solutions.index.other_cta') }}</x-button>
            </div>
        </div>
    </section>

    <x-closing-cta :title="__('home.cta.title')" :lead="__('home.cta.lead')" :button="__('home.cta.button')"
                   photo="cta/owner" :photo-alt="__('home.cta.photo_alt')" />

</x-layouts.site>
