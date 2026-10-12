<?php

namespace App\Helpers;

class SeoHelper
{
    /**
     * Build the canonical URL for the current request.
     *
     * Collapses the ways one page can be reached by several URLs, so crawlers do not
     * treat them as separate pages:
     *  - a front-controller suffix (/index.php, /index.html) is dropped, since
     *    https://site/index.php is the same document as https://site/
     *  - repeated slashes collapse and a trailing slash is removed
     *  - only ?page= is preserved; filter/search/sort params are dropped
     *
     * The host and scheme come from config('app.url'), not from the incoming request, so
     * a visitor arriving over http or on the www host is pointed back at the canonical one.
     */
    public static function canonicalUrl(?string $base = null): string
    {
        $base = rtrim($base ?? (string) config('app.url'), '/');
        $path = (string) (parse_url(request()->url(), PHP_URL_PATH) ?: '/');

        // Collapse repeated slashes first, otherwise a trailing slash hides the
        // front-controller suffix from the next step (/index.php/ would survive).
        $path = (string) preg_replace('#/{2,}#', '/', $path);

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        // /index.php and /index.html are the front controller, not part of the path.
        $path = (string) preg_replace('#/index\.(?:php|html)$#i', '', $path);

        if ($path === '') {
            $path = '/';
        }

        $page = (int) request()->query('page', 1);

        return $base.$path.($page > 1 ? '?page='.$page : '');
    }
}
