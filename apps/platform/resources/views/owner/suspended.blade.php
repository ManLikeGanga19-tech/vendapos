@php
    use App\Domain\Tenancy\Notifications\OwnerNoticeText;

    /** @var \App\Domain\Tenancy\Models\Tenant $tenant */
    $purgeDate = $tenant->purge_after !== null ? OwnerNoticeText::date($tenant->purge_after) : null;
    $supportPhone = config('vendapos.support.phone');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('owner.suspended.title') }} · {{ $tenant->name }}</title>
    <style>
        :root { --bg: #fafaf9; --surface: #ffffff; --text: #1c1917; --muted: #57534e; --border: #e7e5e4; --accent: #b45309; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0c0a09; --surface: #1c1917; --text: #fafaf9; --muted: #a8a29e; --border: #292524; --accent: #f59e0b; }
        }
        *, *::before, *::after { box-sizing: border-box; border-radius: 0; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 34rem; margin: 3rem auto; padding: 0 1rem; }
        section { background: var(--surface); border: 1px solid var(--border); border-top: 4px solid var(--accent); padding: 1.5rem; }
        h1 { margin: 0 0 .25rem; font-size: 1.375rem; }
        .business { margin: 0 0 1.25rem; color: var(--muted); }
        ul { padding-left: 1.25rem; margin: 0 0 1rem; }
        li { margin-bottom: .5rem; }
        .purge { font-weight: 600; }
    </style>
</head>
<body>
<main>
    <section>
        <h1>{{ __('owner.suspended.title') }}</h1>
        <p class="business">{{ $tenant->name }}</p>
        <ul>
            <li>{{ __('owner.suspended.still_works') }}</li>
            <li>{{ __('owner.suspended.blocked') }}</li>
        </ul>
        @if ($purgeDate !== null)
            <p class="purge">{{ __('owner.suspended.purge', ['date' => $purgeDate]) }}</p>
        @endif
        <p>
            @if (filled($supportPhone))
                {{ __('owner.suspended.contact_phone', ['phone' => $supportPhone]) }}
            @else
                {{ __('owner.suspended.contact') }}
            @endif
        </p>
    </section>
</main>
</body>
</html>
