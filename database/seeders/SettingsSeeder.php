<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Seed default marketplace settings (Task 10.4, spec §17/§19).
 *
 * Defaults come from `config/marketplace.php` (`settings_defaults`) with
 * Barokah branding, MYR/RM currency, and English locale. Priority is
 * DB > env > default because env-backed values are baked into the config
 * defaults while database rows always win in SettingsService::all().
 *
 * Existing rows are never overwritten so re-running the seeder is safe.
 * Keys whose business behavior is undefined stay TBC (spec §24) and are
 * stored as toggles/defaults only.
 */
class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = app(SettingsService::class);
        $defaults = config('marketplace.settings_defaults', []);

        foreach ($defaults as $key => $definition) {
            $setting = Setting::query()->where('key', $key)->first();

            if ($setting !== null) {
                $setting->update([
                    'type' => $definition['type'] ?? $setting->type,
                    'group' => $definition['group'] ?? $setting->group,
                    'is_public' => $definition['is_public'] ?? $setting->is_public,
                ]);

                continue;
            }

            $settings->set($key, $definition['value'] ?? null);
        }

        $bannerDefaults = [
            'homepage.banner_1_url' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1600&q=85',
            'homepage.banner_2_url' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=1600&q=85',
            'homepage.banner_3_url' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1600&q=85',
            'homepage.right_top_banner_url' => 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?auto=format&fit=crop&w=1000&q=85',
            'homepage.right_bottom_banner_url' => 'https://images.unsplash.com/photo-1529139574466-a303027c1d8b?auto=format&fit=crop&w=1000&q=85',
        ];

        foreach ($bannerDefaults as $key => $url) {
            $currentValue = Setting::query()->where('key', $key)->value('value');

            if (! $currentValue || str_starts_with((string) $currentValue, 'https://coresg-normal.trae.ai/')) {
                $settings->set($key, $url);
            }
        }

        $settings->forget();
    }
}
