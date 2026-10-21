@php
    use App\Enums\ContactWindow;
    use App\Enums\OutletRange;
    use VendaPOS\Core\Enums\BusinessType;

    // aria wiring shared by every control: invalid state plus hint/error ids.
    $describe = fn (string $name, bool $hasHint = false) => collect([
        $hasHint ? "{$name}-hint" : null,
        $errors->has($name) ? "{$name}-error" : null,
    ])->filter()->implode(' ');
@endphp

<x-layouts.site :title="__('demo.meta.title')" :description="__('demo.meta.description')">
    <section class="aura">
        <div class="container-page grid gap-12 py-12 lg:grid-cols-[1fr_1.1fr] lg:py-20">

            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-coral">{{ __('demo.intro.eyebrow') }}</p>
                <h1 class="text-display font-bold text-balance lg:text-headline-lg">{{ __('demo.intro.title') }}</h1>
                <p class="mt-4 text-lg text-muted">{{ __('demo.intro.lead') }}</p>

                <h2 class="mt-10 font-semibold">{{ __('demo.intro.benefits_title') }}</h2>
                <ul class="mt-4 space-y-4">
                    @foreach (['offline' => 'wifi-off', 'mpesa' => 'smartphone', 'etims' => 'receipt-text', 'branches' => 'network'] as $key => $icon)
                        <li class="flex gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-control bg-coral/10 text-coral">
                                <x-icon :name="$icon" />
                            </span>
                            <span class="text-muted">{{ __("demo.intro.benefits.{$key}") }}</span>
                        </li>
                    @endforeach
                </ul>

                <x-picture name="verticals/shops" :alt="__('demo.intro.photo_alt')" icon="store"
                           class="mt-10 hidden rounded-card lg:block" sizes="(min-width: 1024px) 520px, 0px" />
            </div>

            <div class="rounded-card border border-line bg-canvas/60 p-5 sm:p-8">
                <h2 class="text-2xl font-bold">{{ __('demo.form.title') }}</h2>
                <p class="mt-2 text-muted">{{ __('demo.form.lead') }}</p>

                @if ($errors->any())
                    <div class="mt-6 rounded-control border border-danger/40 bg-danger/10 p-4 text-sm" role="alert" tabindex="-1" id="form-errors">
                        <p class="font-semibold text-ink">{{ __('demo.form.errors_title') }}</p>
                        @if ($errors->hasAny(['form', 'form_token', 'request_id']))
                            <p class="mt-1 text-muted">{{ $errors->first('form') ?: $errors->first('form_token') ?: $errors->first('request_id') }}</p>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('demo.store') }}" class="mt-6 space-y-5" novalidate>
                    @csrf
                    <input type="hidden" name="request_id" value="{{ $requestId }}">
                    <input type="hidden" name="form_token" value="{{ $formToken }}">

                    {{-- Honeypot: off-screen and skipped by keyboards and screen readers. --}}
                    <div class="absolute -left-[9999px]" aria-hidden="true">
                        <label for="{{ \App\Http\Requests\StoreDemoRequest::HONEYPOT }}">{{ __('demo.form.honeypot') }}</label>
                        <input type="text" id="{{ \App\Http\Requests\StoreDemoRequest::HONEYPOT }}" name="{{ \App\Http\Requests\StoreDemoRequest::HONEYPOT }}" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="name" :label="__('demo.fields.name')">
                            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="120" autocomplete="name"
                                   class="field-control" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" aria-describedby="{{ $describe('name') }}">
                        </x-field>

                        <x-field name="business_name" :label="__('demo.fields.business_name')">
                            <input id="business_name" name="business_name" type="text" value="{{ old('business_name') }}" required maxlength="160" autocomplete="organization"
                                   class="field-control" aria-invalid="{{ $errors->has('business_name') ? 'true' : 'false' }}" aria-describedby="{{ $describe('business_name') }}">
                        </x-field>

                        <x-field name="email" :label="__('demo.fields.email')">
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="254" autocomplete="email" inputmode="email"
                                   class="field-control" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="{{ $describe('email') }}">
                        </x-field>

                        <x-field name="phone" :label="__('demo.fields.phone')" :hint="__('demo.hints.phone')">
                            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel" inputmode="tel" placeholder="0712 345 678"
                                   class="field-control" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" aria-describedby="{{ $describe('phone', true) }}">
                        </x-field>

                        <x-field name="business_type" :label="__('demo.fields.business_type')">
                            <select id="business_type" name="business_type" required class="field-control"
                                    aria-invalid="{{ $errors->has('business_type') ? 'true' : 'false' }}" aria-describedby="{{ $describe('business_type') }}">
                                <option value="">{{ __('demo.form.choose') }}</option>
                                @foreach (BusinessType::cases() as $type)
                                    <option value="{{ $type->value }}" @selected(old('business_type') === $type->value)>{{ __("demo.business_types.{$type->value}") }}</option>
                                @endforeach
                            </select>
                        </x-field>

                        <x-field name="outlets" :label="__('demo.fields.outlets')">
                            <select id="outlets" name="outlets" required class="field-control"
                                    aria-invalid="{{ $errors->has('outlets') ? 'true' : 'false' }}" aria-describedby="{{ $describe('outlets') }}">
                                <option value="">{{ __('demo.form.choose') }}</option>
                                @foreach (OutletRange::cases() as $range)
                                    <option value="{{ $range->value }}" @selected(old('outlets') === $range->value)>{{ __("demo.outlets.{$range->value}") }}</option>
                                @endforeach
                            </select>
                        </x-field>
                    </div>

                    <x-field name="contact_window" :label="__('demo.fields.contact_window')" optional>
                        <select id="contact_window" name="contact_window" class="field-control"
                                aria-invalid="{{ $errors->has('contact_window') ? 'true' : 'false' }}" aria-describedby="{{ $describe('contact_window') }}">
                            <option value="">{{ __('demo.form.any_time') }}</option>
                            @foreach (ContactWindow::cases() as $window)
                                <option value="{{ $window->value }}" @selected(old('contact_window') === $window->value)>{{ __("demo.contact_windows.{$window->value}") }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field name="message" :label="__('demo.fields.message')" :hint="__('demo.hints.message')" optional>
                        <textarea id="message" name="message" rows="3" maxlength="2000" class="field-control"
                                  aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}" aria-describedby="{{ $describe('message', true) }}">{{ old('message') }}</textarea>
                    </x-field>

                    <div>
                        <label class="flex cursor-pointer items-start gap-3 text-sm text-muted">
                            <input type="checkbox" name="consent" value="1" @checked(old('consent')) required
                                   class="mt-0.5 size-5 shrink-0 accent-coral" aria-invalid="{{ $errors->has('consent') ? 'true' : 'false' }}" aria-describedby="{{ $describe('consent') }}">
                            <span>{{ __('demo.form.consent') }}</span>
                        </label>
                        @error('consent')
                            <p id="consent-error" class="mt-1.5 flex items-center gap-1.5 text-sm text-danger"><x-icon name="x" class="size-4" />{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-card bg-coral px-6 font-semibold text-canvas shadow-glow transition hover:bg-coral-hover active:scale-[0.98]">
                        {{ __('demo.form.submit') }}
                        <x-icon name="arrow-right" class="size-4" />
                    </button>
                </form>

                <section class="mt-8 border-t border-line pt-6 text-sm text-muted" aria-labelledby="privacy-title">
                    <h3 id="privacy-title" class="flex items-center gap-2 font-semibold text-ink">
                        <x-icon name="lock" class="size-4" />{{ __('demo.privacy.title') }}
                    </h3>
                    <p class="mt-2">{{ __('demo.privacy.use') }}</p>
                    <p class="mt-2">
                        <x-placeholder :confirmed="config('marketing.leads.retention_confirmed')">{{ __('demo.privacy.retention', ['months' => config('marketing.leads.retention_months')]) }}</x-placeholder>
                    </p>
                    <p class="mt-2">{{ __('demo.privacy.rights', ['email' => config('marketing.contact.email')]) }}</p>
                    <p class="mt-2">{{ __('demo.privacy.law') }}</p>
                </section>
            </div>
        </div>
    </section>
</x-layouts.site>
