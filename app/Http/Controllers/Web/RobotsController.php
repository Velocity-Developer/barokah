<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\Response;

/**
 * robots.txt, written per environment so it always points at this site's
 * sitemap. Account and checkout pages are kept out of search results.
 */
class RobotsController extends Controller
{
    public function __invoke(SettingsService $settings): Response
    {
        $maintenance = (bool) $settings->get('general.maintenance_mode', false);

        $lines = ['User-agent: *'];

        if ($maintenance) {
            // Nothing worth indexing while the shop is closed.
            $lines[] = 'Disallow: /';
        } else {
            foreach (['/admin', '/seller', '/checkout', '/cart', '/profile', '/settings', '/chat'] as $path) {
                $lines[] = 'Disallow: '.$path;
            }

            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
