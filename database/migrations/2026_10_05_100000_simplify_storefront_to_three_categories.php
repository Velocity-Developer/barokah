<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Client revision (Oct 2026): the store sells under three categories only —
 * Fashion, Food and Lain-lain — in purple and white instead of orange.
 *
 * Keripik becomes Food, Kerudung becomes Fashion (Hijab products move in
 * with it), and products of any other category move to Lain-lain. Emptied
 * categories are switched off, not deleted. Brand colors and the SEO copy
 * are only replaced while they still hold the old defaults, so values an
 * admin has since edited are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $fashion = $this->claim('fashion', ['kerudung', 'hijab'], 'Fashion', 'Fashion and accessories for everyday style.', 0);
            $food = $this->claim('food', ['keripik'], 'Food', 'Keripik, snacks and other food.', 1);
            $other = $this->claim('lain-lain', [], 'Lain-lain', 'Other products.', 2);

            $keep = [$fashion->id, $food->id, $other->id];

            foreach (Category::query()->whereNotIn('id', $keep)->get() as $category) {
                $target = in_array($category->slug, ['hijab', 'kerudung'], true) ? $fashion : $other;

                Product::query()->where('category_id', $category->id)->update(['category_id' => $target->id]);
                $category->update(['is_active' => false]);
            }
        });

        $settings = app(SettingsService::class);

        $colors = [
            'branding.primary_color' => ['#ee4d2d', '#8b3fa8'],
            'branding.primary_hover_color' => ['#d94426', '#74308f'],
            'branding.primary_soft_color' => ['#fff1ed', '#f6ecfb'],
            'branding.secondary_color' => ['#113366', '#4b1d63'],
        ];

        foreach ($colors as $key => [$old, $new]) {
            $current = $settings->get($key);

            if ($current === null || $current === '' || strcasecmp((string) $current, $old) === 0) {
                $settings->set($key, $new);
            }
        }

        $copy = [
            'marketplace.description' => 'Malaysian multi-seller marketplace for fashion, food and more from trusted local sellers.',
            'seo.meta_title' => 'Barokah Marketplace – Fashion, Food & More',
            'seo.meta_description' => 'Shop fashion, food and more from trusted sellers across Malaysia. Pay securely with FPX or DuitNow.',
            'seo.keywords' => 'barokah, marketplace malaysia, fashion, muslimah fashion, food, snacks',
        ];

        foreach ($copy as $key => $value) {
            if (stripos((string) $settings->get($key, ''), 'kerudung') !== false) {
                $settings->set($key, $value);
            }
        }
    }

    public function down(): void
    {
        // Data migration: restore the pre-deploy database backup to undo it.
    }

    /**
     * Reuse the category already holding $slug, else the first legacy slug
     * found, else create it; then give it the new name and position.
     *
     * @param  list<string>  $legacySlugs
     */
    private function claim(string $slug, array $legacySlugs, string $name, string $description, int $sortOrder): Category
    {
        $category = Category::query()->where('slug', $slug)->first();

        foreach ($legacySlugs as $legacy) {
            $category ??= Category::query()->where('slug', $legacy)->first();
        }

        $category ??= new Category;
        $category->fill([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ])->save();

        return $category;
    }
};
