<?php

namespace App\Http\Controllers;

use App\Models\CustomCreation;
use App\Models\Deal;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function robots(): Response
    {
        $sitemapUrl = rtrim(config('app.url'), '/') . '/sitemap.xml';

        $content = <<<TXT
User-agent: *
Allow: /

Disallow: /admin/
Disallow: /delivery-portal/
Disallow: /pickup-portal/
Disallow: /api/
Disallow: /cart/
Disallow: /checkout/
Disallow: /account/

Sitemap: {$sitemapUrl}
TXT;

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }

    public function sitemap(): Response
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $urls = [];

        // ── Static public pages ────────────────────────────────────────
        $staticPages = [
            '/'                  => ['changefreq' => 'daily',  'priority' => '1.0'],
            '/shop'              => ['changefreq' => 'daily',  'priority' => '0.9'],
            '/deals'             => ['changefreq' => 'daily',  'priority' => '0.8'],
            '/custom-creations'  => ['changefreq' => 'weekly', 'priority' => '0.7'],
            '/about'             => ['changefreq' => 'monthly', 'priority' => '0.5'],
            '/contact'           => ['changefreq' => 'monthly', 'priority' => '0.5'],
            '/return-policy'     => ['changefreq' => 'yearly',  'priority' => '0.3'],
            '/privacy-policy'    => ['changefreq' => 'yearly',  'priority' => '0.3'],
            '/cookie-policy'     => ['changefreq' => 'yearly',  'priority' => '0.3'],
        ];

        foreach ($staticPages as $path => $attrs) {
            $urls[] = array_merge(['loc' => $baseUrl . $path], $attrs);
        }

        // ── Active products ────────────────────────────────────────────
        Product::where(function ($q) {
                $q->where('status', 'active')->where('is_active', true);
            })
            ->orWhere(function ($q) {
                $q->whereNull('status')->where('is_active', true);
            })
            ->select('id', 'updated_at')
            ->orderBy('id')
            ->chunk(200, function ($products) use (&$urls) {
                foreach ($products as $product) {
                    $urls[] = [
                        'loc'        => url('/products/' . $product->id),
                        'lastmod'    => $product->updated_at->toIso8601String(),
                        'changefreq' => 'weekly',
                        'priority'   => '0.8',
                    ];
                }
            });

        // ── Live deals ─────────────────────────────────────────────────
        Deal::live()
            ->select('id', 'updated_at')
            ->orderBy('id')
            ->chunk(200, function ($deals) use (&$urls) {
                foreach ($deals as $deal) {
                    $urls[] = [
                        'loc'        => url('/deals/' . $deal->id),
                        'lastmod'    => $deal->updated_at->toIso8601String(),
                        'changefreq' => 'weekly',
                        'priority'   => '0.7',
                    ];
                }
            });

        // ── Active custom creations ────────────────────────────────────
        CustomCreation::active()
            ->select('id', 'updated_at')
            ->orderBy('id')
            ->chunk(200, function ($creations) use (&$urls) {
                foreach ($creations as $creation) {
                    $urls[] = [
                        'loc'        => url('/custom-creations/' . $creation->id),
                        'lastmod'    => $creation->updated_at->toIso8601String(),
                        'changefreq' => 'weekly',
                        'priority'   => '0.7',
                    ];
                }
            });

        // ── Build XML ──────────────────────────────────────────────────
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . e($url['loc']) . "</loc>\n";
            if (! empty($url['lastmod'])) {
                $xml .= "    <lastmod>" . e($url['lastmod']) . "</lastmod>\n";
            }
            $xml .= "    <changefreq>" . e($url['changefreq'] ?? 'monthly') . "</changefreq>\n";
            $xml .= "    <priority>" . e($url['priority'] ?? '0.5') . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
