<?php

use App\Services\SettingsService;

it('uses the uploaded branding favicon as the site icon', function () {
    app(SettingsService::class)->set('branding.favicon_url', 'branding/site-icon.png');

    $html = $this->withoutVite()->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('rel="icon" href="'.url('storage/branding/site-icon.png').'"')
        ->toContain('rel="apple-touch-icon" href="'.url('storage/branding/site-icon.png').'"')
        ->not->toContain('href="/favicon.svg"');
});

it('falls back to the bundled icons when no favicon is uploaded', function () {
    $html = $this->withoutVite()->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('href="/favicon.ico"')
        ->toContain('href="/favicon.svg"');
});
