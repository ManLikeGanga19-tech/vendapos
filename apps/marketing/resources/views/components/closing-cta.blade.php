@props([
    'title',
    'lead',
    'button',
    'photo',
    'photoAlt',
    'photoIcon' => 'trending-up',
])

{{-- Shared end-of-page call to action: book a demo or call sales, beside a photo. --}}
<section id="get-started" class="scroll-mt-20 py-16 lg:py-24">
    <div class="container-page">
        <div class="grid items-center gap-10 overflow-hidden border border-line bg-surface lg:grid-cols-2">
            <div class="p-8 lg:p-12">
                <h2 class="text-headline font-bold text-balance lg:text-headline-lg">{{ $title }}</h2>
                <p class="mt-4 text-lg text-muted">{{ $lead }}</p>
                <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <x-button :href="route('demo')" size="lg" icon="arrow-right">{{ $button }}</x-button>
                    <p class="flex flex-wrap items-center gap-2 text-sm text-muted">
                        {{ __('common.call_sales') }}
                        <x-placeholder :confirmed="config('marketing.contact.confirmed')">{{ config('marketing.contact.phone') }}</x-placeholder>
                    </p>
                </div>
            </div>
            <x-picture :name="$photo" :alt="$photoAlt" :icon="$photoIcon"
                       ratio="4/3" sizes="(min-width: 1024px) 600px, 100vw" class="h-full" />
        </div>
    </div>
</section>
