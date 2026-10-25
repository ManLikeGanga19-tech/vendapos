@php
    use Illuminate\Support\Carbon;

    /** @var string $document  privacy|terms */
    $base = "legal.{$document}";
    $replace = ['months' => config('marketing.leads.retention_months')];
    $updated = Carbon::parse(config('marketing.legal.updated'))->locale(app()->getLocale())->translatedFormat('j F Y');
    $company = config('marketing.company');
@endphp

<x-layouts.site :title="__($base.'.meta.title')" :description="__($base.'.meta.description')">

    <section class="border-b border-line py-12 lg:py-16">
        <div class="container-page max-w-4xl">
            <h1 class="text-display font-bold lg:text-display-lg">{{ __($base.'.title') }}</h1>
            <p class="mt-4 text-lg text-muted">{{ __($base.'.lead') }}</p>
            <p class="mt-4 text-sm text-subtle">{{ __('legal.common.updated', ['date' => $updated]) }}</p>

            @unless (config('marketing.legal.reviewed'))
                <p class="mt-6 flex gap-3 border-l-2 border-pending bg-pending/10 p-4 text-sm text-ink" role="note">
                    <x-icon name="clock" class="size-5 text-pending" />{{ __('legal.common.draft') }}
                </p>
            @endunless
        </div>
    </section>

    <div class="container-page grid gap-12 py-12 lg:grid-cols-[220px_1fr] lg:py-16">
        {{-- Table of contents: sticky beside the text on desktop, a list above it on phones. --}}
        <nav aria-labelledby="toc-title" class="lg:sticky lg:top-28 lg:self-start">
            <h2 id="toc-title" class="text-xs font-semibold uppercase tracking-widest text-subtle">{{ __('legal.common.contents') }}</h2>
            <ol class="mt-4 space-y-2 border-l border-line text-sm">
                @foreach (__($base.'.sections') as $key => $section)
                    <li><a href="#{{ $key }}" class="-ml-px block border-l border-transparent py-1 pl-4 text-muted transition-colors hover:border-coral hover:text-ink">{{ $section['title'] }}</a></li>
                @endforeach
            </ol>
        </nav>

        <div class="max-w-3xl">
            <section class="reveal border border-line bg-surface p-6" aria-labelledby="company-title">
                <h2 id="company-title" class="font-semibold">{{ __('legal.common.company_title') }}</h2>
                <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[auto_1fr]">
                    @foreach ($company as $field => $value)
                        <dt class="text-muted">{{ __("legal.common.fields.{$field}") }}</dt>
                        <dd>
                            <x-placeholder :confirmed="$value !== null">{{ $value ?? '—' }}</x-placeholder>
                        </dd>
                    @endforeach
                </dl>
            </section>

            @foreach (__($base.'.sections') as $key => $section)
                <section id="{{ $key }}" class="reveal scroll-mt-28 pt-10" aria-labelledby="{{ $key }}-title">
                    <h2 id="{{ $key }}-title" class="text-xl font-semibold lg:text-2xl">
                        <span class="tabular mr-2 text-coral">{{ $loop->iteration }}.</span>{{ $section['title'] }}
                    </h2>
                    <div class="mt-4 space-y-4 leading-relaxed text-muted">
                        @foreach (array_keys($section['body'] ?? []) as $i)
                            <p>{{ __("{$base}.sections.{$key}.body.{$i}", $replace) }}</p>
                        @endforeach
                        @if (! empty($section['list']))
                            <ul class="space-y-2">
                                @foreach (array_keys($section['list']) as $i)
                                    <li class="flex gap-3"><span class="mt-2.5 size-1.5 shrink-0 bg-coral" aria-hidden="true"></span>{{ __("{$base}.sections.{$key}.list.{$i}", $replace) }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @foreach (array_keys($section['after'] ?? []) as $i)
                            <p>{{ __("{$base}.sections.{$key}.after.{$i}", $replace) }}</p>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <p class="mt-12 border-t border-line pt-6 text-sm text-subtle">{{ __('legal.common.precedence') }}</p>
        </div>
    </div>

</x-layouts.site>
