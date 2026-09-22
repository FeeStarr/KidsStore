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
        $sitemapUrl = rtrim((string) config('app.url'), '/') . '/sitemap.xml';

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
        $baseUrl = rtrim((string) config('app.url'), '/');
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
        try {
            Product::where(function ($q) {
                    $q->where('status', 'active')->where('is_active', true);
                })
                ->orWhere(function ($q) {
                    $q->whereNull('status')->where('is_active', true);
                })
                ->select('id', 'updated_at')
                ->orderBy('id')
                ->chunk(200, function ($products) use (&$urls, $baseUrl) {
                    foreach ($products as $product) {
                        $urls[] = [
                            'loc'        => $baseUrl . '/products/' . $product->id,
                            'lastmod'    => $this->lastModified($product),
                            'changefreq' => 'weekly',
                            'priority'   => '0.8',
                        ];
                    }
                });
        } catch (\Throwable $e) {
            report($e);
        }

        // ── Live deals ─────────────────────────────────────────────────
        try {
            Deal::live()
                ->select('id', 'updated_at')
                ->orderBy('id')
                ->chunk(200, function ($deals) use (&$urls, $baseUrl) {
                    foreach ($deals as $deal) {
                        $urls[] = [
                            'loc'        => $baseUrl . '/deals/' . $deal->id,
                            'lastmod'    => $this->lastModified($deal),
                            'changefreq' => 'weekly',
                            'priority'   => '0.7',
                        ];
                    }
                });
        } catch (\Throwable $e) {
            report($e);
        }

        // ── Active custom creations ────────────────────────────────────
        try {
            CustomCreation::active()
                ->select('id', 'updated_at')
                ->orderBy('id')
                ->chunk(200, function ($creations) use (&$urls, $baseUrl) {
                    foreach ($creations as $creation) {
                        $urls[] = [
                            'loc'        => $baseUrl . '/custom-creations/' . $creation->id,
                            'lastmod'    => $this->lastModified($creation),
                            'changefreq' => 'weekly',
                            'priority'   => '0.7',
                        ];
                    }
                });
        } catch (\Throwable $e) {
            report($e);
        }

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

    private function lastModified($model): ?string
    {
        $timestamp = $model->updated_at ?? $model->created_at;

        if ($timestamp === null) {
            return null;
        }

        try {
            return $timestamp->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
