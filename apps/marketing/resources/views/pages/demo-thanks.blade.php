<x-layouts.site :title="__('demo.meta.title')" :description="__('demo.meta.description')">
    <section class="aura">
        <div class="container-page flex flex-col items-center py-20 text-center lg:py-32">
            <span class="flex size-16 items-center justify-center rounded-full bg-success/10 text-success">
                <x-icon name="circle-check" class="size-9" />
            </span>
            <h1 class="mt-6 max-w-2xl text-headline font-bold text-balance lg:text-headline-lg">{{ __('demo.thanks.title') }}</h1>
            <p class="mt-4 max-w-xl text-lg text-muted">{{ __('demo.thanks.body') }}</p>
            <x-button :href="route('home')" variant="secondary" class="mt-10">{{ __('demo.thanks.back') }}</x-button>
        </div>
    </section>
</x-layouts.site>
