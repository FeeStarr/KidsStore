<?php

namespace Tests\Feature;

use App\Helpers\SeoHelper;
use Illuminate\Http\Request;
use Tests\TestCase;

class SeoCanonicalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://kidsflairr.com.ng']);
    }

    /**
     * Swaps in a synthetic request so front-controller URLs, which no route matches,
     * can still be exercised.
     */
    private function canonicalFor(string $uri): string
    {
        app()->instance('request', Request::create($uri, 'GET'));

        return SeoHelper::canonicalUrl();
    }

    public function test_root_canonicalises_to_a_single_slash(): void
    {
        $this->assertSame('https://kidsflairr.com.ng/', $this->canonicalFor('https://kidsflairr.com.ng/'));
    }

    /**
     * The real defect: /index.php serves the homepage at a second URL and was emitting a
     * self-referential canonical, so the two looked like separate indexable pages.
     */
    public function test_index_php_canonicalises_to_the_root(): void
    {
        $this->assertSame('https://kidsflairr.com.ng/', $this->canonicalFor('https://kidsflairr.com.ng/index.php'));
    }

    public function test_index_html_and_uppercase_suffix_are_normalised(): void
    {
        $this->assertSame('https://kidsflairr.com.ng/', $this->canonicalFor('https://kidsflairr.com.ng/index.html'));
        $this->assertSame('https://kidsflairr.com.ng/', $this->canonicalFor('https://kidsflairr.com.ng/INDEX.PHP'));
    }

    public function test_query_string_on_index_php_still_yields_the_root(): void
    {
        $this->assertSame('https://kidsflairr.com.ng/', $this->canonicalFor('https://kidsflairr.com.ng/index.php?x=1'));
    }

    public function test_nested_front_controller_is_stripped(): void
    {
        $this->assertSame(
            'https://kidsflairr.com.ng/shop',
            $this->canonicalFor('https://kidsflairr.com.ng/shop/index.php')
        );
    }

    public function test_trailing_slash_is_removed(): void
    {
        $this->assertSame('https://kidsflairr.com.ng/about', $this->canonicalFor('https://kidsflairr.com.ng/about/'));
        $this->assertSame('https://kidsflairr.com.ng/shop', $this->canonicalFor('https://kidsflairr.com.ng/shop/'));
    }

    public function test_trailing_slash_after_front_controller_is_removed(): void
    {
        // Order matters: strip the slash first, then the suffix.
        $this->assertSame('https://kidsflairr.com.ng/', $this->canonicalFor('https://kidsflairr.com.ng/index.php/'));
    }

    public function test_duplicate_slashes_collapse(): void
    {
        $this->assertSame('https://kidsflairr.com.ng/shop', $this->canonicalFor('https://kidsflairr.com.ng//shop'));
        $this->assertSame('https://kidsflairr.com.ng/shop', $this->canonicalFor('https://kidsflairr.com.ng/shop//'));
    }

    public function test_pagination_is_preserved(): void
    {
        $this->assertSame('https://kidsflairr.com.ng/shop?page=3', $this->canonicalFor('https://kidsflairr.com.ng/shop?page=3'));
        $this->assertSame('https://kidsflairr.com.ng/shop', $this->canonicalFor('https://kidsflairr.com.ng/shop?page=1'));
    }

    public function test_filter_and_sort_params_are_dropped(): void
    {
        $this->assertSame('https://kidsflairr.com.ng/shop', $this->canonicalFor('https://kidsflairr.com.ng/shop?sort=price&category=ankara'));
        $this->assertSame('https://kidsflairr.com.ng/shop', $this->canonicalFor('https://kidsflairr.com.ng/shop?q=dress'));
    }

    /**
     * A visitor arriving on a non-canonical host must still be pointed at the canonical one.
     */
    public function test_www_and_http_visitors_get_the_canonical_host(): void
    {
        $this->assertSame('https://kidsflairr.com.ng/about', $this->canonicalFor('http://kidsflairr.com.ng/about'));
        $this->assertSame('https://kidsflairr.com.ng/about', $this->canonicalFor('https://www.kidsflairr.com.ng/about'));
    }

    public function test_rendered_pages_emit_a_single_canonical_tag(): void
    {
        config(['app.url' => 'https://kidsflairr.com.ng']);

        $html = $this->get('/about')->assertOk()->getContent();

        $this->assertSame(
            1,
            preg_match_all('/<link rel="canonical" href="([^"]+)"/', $html, $matches),
            'Exactly one canonical tag should be present.'
        );
        $this->assertSame('https://kidsflairr.com.ng/about', $matches[1][0]);
    }

    public function test_rendered_paginated_page_keeps_page_in_canonical(): void
    {
        config(['app.url' => 'https://kidsflairr.com.ng']);

        $html = $this->get('/shop?page=2')->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $matches));
        $this->assertSame('https://kidsflairr.com.ng/shop?page=2', $matches[1]);
    }

    public function test_product_page_canonical_is_its_own_url(): void
    {
        config(['app.url' => 'https://kidsflairr.com.ng']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $matches));

        $this->assertSame('https://kidsflairr.com.ng/', $matches[1]);
    }
}
