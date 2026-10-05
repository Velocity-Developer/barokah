<?php

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;

/**
 * Client revision (Oct 2026): the shop trades as Nextbeli, with "Harga borong"
 * under the logo. "Barokah" is swapped for "Nextbeli" only in settings that
 * still carry it, so copy an admin has rewritten is left alone. The logo and
 * favicon files themselves are uploaded through Admin → Settings → Branding.
 */
return new class extends Migration
{
    private const KEYS = [
        'branding.site_name',
        'general.site_tagline',
        'marketplace.name',
        'marketplace.description',
        'seo.meta_title',
        'seo.meta_description',
        'seo.keywords',
        'email.from_name',
    ];

    public function up(): void
    {
        // Only stored values are touched; a fresh install keeps the config defaults.
        $stored = Setting::query()->pluck('key')->all();

        if (! in_array('branding.logo_url', $stored, true)) {
            return;
        }

        $settings = app(SettingsService::class);

        foreach (array_intersect(self::KEYS, $stored) as $key) {
            $value = $settings->get($key);

            if (is_string($value) && stripos($value, 'barokah') !== false) {
                $settings->set($key, str_replace(['Barokah', 'barokah', 'BAROKAH'], ['Nextbeli', 'nextbeli', 'NEXTBELI'], $value));
            }
        }

        if (trim((string) $settings->get('branding.logo_tagline', '')) === '') {
            $settings->set('branding.logo_tagline', 'Harga borong');
        }
    }

    public function down(): void
    {
        // Data migration: restore the pre-deploy database backup to undo it.
    }
};
