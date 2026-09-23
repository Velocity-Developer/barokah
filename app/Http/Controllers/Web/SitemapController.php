<?php

namespace App\Http\Controllers\Web;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use DateTimeInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * XML sitemap for search engines: the public pages, every category, every
 * active store and the products they can actually buy.
 *
 * Only pages a shopper can open are listed, so a suspended store or a draft
 * product never reaches Google.
 */
class SitemapController extends Controller
{
    private const CACHE_KEY = 'sitemap.xml';

    private const CACHE_MINUTES = 60;

    /** Sitemaps cap at 50,000 URLs; stay well inside it. */
    private const MAX_URLS = 45000;

    public function __invoke(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_MINUTES), fn (): string => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        $urls = [
            ['loc' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('products.index'), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => route('flash-sale.index'), 'changefreq' => 'daily', 'priority' => '0.7'],
            ['loc' => route('coupons.index'), 'changefreq' => 'weekly', 'priority' => '0.5'],
            ['loc' => route('help'), 'changefreq' => 'monthly', 'priority' => '0.3'],
            ['loc' => route('tracking.index'), 'changefreq' => 'monthly', 'priority' => '0.3'],
        ];

        foreach (Category::query()->where('is_active', true)->orderBy('slug')->get(['slug', 'updated_at']) as $category) {
            $urls[] = [
                'loc' => route('products.index', ['category' => $category->slug]),
                'lastmod' => $this->stamp($category->updated_at),
                'changefreq' => 'daily',
                'priority' => '0.7',
            ];
        }

        foreach (Seller::query()->where('status', SellerStatus::Active)->orderBy('slug')->get(['slug', 'updated_at']) as $seller) {
            $urls[] = [
                'loc' => route('sellers.show', $seller->slug),
                'lastmod' => $this->stamp($seller->updated_at),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }

        Product::query()
            ->active()
            ->orderBy('id')
            ->select(['slug', 'updated_at'])
            ->chunk(1000, function ($products) use (&$urls): bool {
                foreach ($products as $product) {
                    $urls[] = [
                        'loc' => route('products.show', $product->slug),
                        'lastmod' => $this->stamp($product->updated_at),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                    ];
                }

                return count($urls) < self::MAX_URLS;
            });

        return $this->render(array_slice($urls, 0, self::MAX_URLS));
    }

    /**
     * @param  list<array<string, string>>  $urls
     */
    private function render(array $urls): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($urls as $url) {
            $lines[] = '    <url>';
            $lines[] = '        <loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>';

            if (isset($url['lastmod'])) {
                $lines[] = '        <lastmod>'.$url['lastmod'].'</lastmod>';
            }

            $lines[] = '        <changefreq>'.$url['changefreq'].'</changefreq>';
            $lines[] = '        <priority>'.$url['priority'].'</priority>';
            $lines[] = '    </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    private function stamp(?DateTimeInterface $date): string
    {
        return ($date === null ? now() : Carbon::instance($date))->toAtomString();
    }

    /** Lets a command or a content change rebuild the file on the next hit. */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
