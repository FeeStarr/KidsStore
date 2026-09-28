<?php

namespace Tests\Feature;

use App\Models\CustomCreation;
use App\Models\Product;
use Tests\TestCase;

class SeoTest extends TestCase
{
    public function test_robots_txt_returns_200(): void
    {
        $this->get('/robots.txt')->assertOk();
    }

    public function test_robots_txt_content_type_is_text_plain(): void
    {
        $this->get('/robots.txt')
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_robots_txt_allows_root(): void
    {
        $this->get('/robots.txt')
            ->assertSee('Allow: /');
    }

    public function test_robots_txt_disallows_admin(): void
    {
        $this->get('/robots.txt')
            ->assertSee('Disallow: /admin/');
    }

    public function test_robots_txt_disallows_delivery_portal(): void
    {
        $this->get('/robots.txt')
            ->assertSee('Disallow: /delivery-portal/');
    }

    public function test_robots_txt_disallows_pickup_portal(): void
    {
        $this->get('/robots.txt')
            ->assertSee('Disallow: /pickup-portal/');
    }

    public function test_robots_txt_disallows_api(): void
    {
        $this->get('/robots.txt')
            ->assertSee('Disallow: /api/');
    }

    public function test_robots_txt_disallows_cart(): void
    {
        $this->get('/robots.txt')
            ->assertSee('Disallow: /cart/');
    }

    public function test_robots_txt_disallows_checkout(): void
    {
        $this->get('/robots.txt')
            ->assertSee('Disallow: /checkout/');
    }

    public function test_robots_txt_disallows_account(): void
    {
        $this->get('/robots.txt')
            ->assertSee('Disallow: /account/');
    }

    public function test_robots_txt_references_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertSee('Sitemap:');
    }

    public function test_sitemap_returns_200(): void
    {
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_sitemap_content_type_is_xml(): void
    {
        $this->get('/sitemap.xml')
            ->assertHeader('Content-Type', 'application/xml');
    }

    public function test_sitemap_is_valid_xml(): void
    {
        $content = $this->get('/sitemap.xml')->getContent();
        $this->assertNotFalse(simplexml_load_string($content), 'Sitemap is not valid XML');
    }

    public function test_sitemap_contains_homepage(): void
    {
        $this->get('/sitemap.xml')
            ->assertSeeText(config('app.url') . '/');
    }

    public function test_sitemap_contains_active_product(): void
    {
        Product::create([
            'name'          => 'Pink Dress',
            'slug'          => 'pink-dress',
            'sku'           => 'PD001',
            'selling_price' => 5000,
            'status'        => 'active',
            'is_active'     => true,
        ]);

        $this->get('/sitemap.xml')
            ->assertSeeText('/products/');
    }

    public function test_sitemap_excludes_inactive_product(): void
    {
        Product::create([
            'name'          => 'Hidden Product',
            'slug'          => 'hidden-product',
            'sku'           => 'HP001',
            'selling_price' => 3000,
            'status'        => 'inactive',
            'is_active'     => false,
        ]);

        $this->get('/sitemap.xml')
            ->assertDontSee('Hidden Product');
    }

    public function test_sitemap_contains_active_custom_creation(): void
    {
        CustomCreation::create([
            'title'       => 'Custom Frock',
            'image_path'  => 'creations/frock.jpg',
            'price'       => 8000,
            'is_active'   => true,
            'category'    => 'frocks',
        ]);

        $this->get('/sitemap.xml')
            ->assertSeeText('/custom-creations/');
    }

    public function test_sitemap_excludes_inactive_custom_creation(): void
    {
        CustomCreation::create([
            'title'       => 'Hidden Creation',
            'image_path'  => 'creations/hidden.jpg',
            'price'       => 4000,
            'is_active'   => false,
            'category'    => 'frocks',
        ]);

        $this->get('/sitemap.xml')
            ->assertDontSee('Hidden Creation');
    }

    public function test_sitemap_excludes_admin_urls(): void
    {
        $this->get('/sitemap.xml')
            ->assertDontSee('/admin/');
    }

    public function test_sitemap_excludes_auth_urls(): void
    {
        $this->get('/sitemap.xml')
            ->assertDontSee('/login')
            ->assertDontSee('/register')
            ->assertDontSee('/account/');
    }

    public function test_sitemap_excludes_cart_urls(): void
    {
        $this->get('/sitemap.xml')
            ->assertDontSee('/cart/')
            ->assertDontSee('/checkout/');
    }

    public function test_sitemap_uses_https(): void
    {
        config(['app.url' => 'https://kidsflairr.com.ng']);

        Product::create([
            'name'          => 'Secure Product',
            'slug'          => 'secure-product',
            'sku'           => 'SP001',
            'selling_price' => 2000,
            'status'        => 'active',
            'is_active'     => true,
        ]);

        $this->get('/sitemap.xml')
            ->assertSee('https://kidsflairr.com.ng');
    }

    public function test_sitemap_no_query_strings_in_urls(): void
    {
        $content = $this->get('/sitemap.xml')->getContent();

        preg_match_all('/<loc>(.*?)<\/loc>/', $content, $matches);

        $this->assertNotEmpty($matches[1], 'Sitemap should contain at least one URL');
        foreach ($matches[1] as $url) {
            $this->assertStringNotContainsString('?', $url, "URL should not contain query string: {$url}");
        }
    }

    public function test_homepage_has_canonical(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="' . rtrim(config('app.url'), '/') . '/">', false);
    }

    public function test_shop_page_has_self_referencing_canonical(): void
    {
        $this->get('/shop')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="' . config('app.url') . '/shop">', false);
    }

    public function test_pagination_keeps_page_param_in_canonical(): void
    {
        $this->get('/shop?page=2')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="' . config('app.url') . '/shop?page=2">', false);
    }

    public function test_filter_params_are_dropped_from_canonical(): void
    {
        $this->get('/shop?category=2&q=shoes&sort=price_asc')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="' . config('app.url') . '/shop">', false);
    }

    public function test_every_page_has_exactly_one_canonical(): void
    {
        $html = $this->get('/shop')->assertOk()->getContent();

        $this->assertSame(
            1,
            substr_count($html, '<link rel="canonical"'),
            'Page must contain exactly one canonical link'
        );
    }
}
