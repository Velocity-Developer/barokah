<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Under maintenance') }} · {{ $branding['site_name'] }}</title>
    @if ($branding['favicon_url'])
        <link rel="icon" href="{{ $branding['favicon_url'] }}">
    @else
        <link rel="icon" href="/favicon.ico" sizes="any">
    @endif
    <style>
        :root { --brand: {{ $branding['primary_color'] }}; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px;
            font-family: Arial, Helvetica, 'Noto Sans', sans-serif; color: #1f2937; background: #f5f5f5; }
        main { width: 100%; max-width: 440px; background: #fff; border-radius: 12px; padding: 32px 24px; text-align: center;
            box-shadow: 0 1px 3px rgb(0 0 0 / .08); border-top: 4px solid var(--brand); }
        .logo { max-height: 56px; max-width: 180px; object-fit: contain; }
        .mark { width: 56px; height: 56px; margin: 0 auto; border-radius: 12px; display: grid; place-items: center;
            background: var(--brand); color: #fff; font-size: 26px; font-weight: 700; }
        h1 { margin: 20px 0 8px; font-size: 22px; }
        p { margin: 0; color: #6b7280; line-height: 1.55; font-size: 15px; }
        .site { margin-top: 20px; font-size: 13px; color: #9ca3af; }
        a { color: var(--brand); }
    </style>
</head>
<body>
    <main>
        @if ($branding['logo_url'])
            <img class="logo" src="{{ $branding['logo_url'] }}" alt="{{ $branding['site_name'] }}">
        @else
            <div class="mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($branding['site_name'], 0, 1)) }}</div>
        @endif
        <h1>{{ __('We will be right back') }}</h1>
        <p>{{ __(':site is under maintenance while we make some improvements. Please check back in a little while.', ['site' => $branding['site_name']]) }}</p>
        <p class="site">{{ $branding['tagline'] ?? $branding['site_name'] }} · <a href="{{ route('login') }}">{{ __('Staff login') }}</a></p>
    </main>
</body>
</html>
