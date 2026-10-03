<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Tests\TestCase;

class HomeLatestGoodiesTest extends TestCase
{
    /**
     * Creates $count products spread across $categories (round-robin), with
     * staggered created_at so "newest 30" is unambiguous - products created in
     * the same second would otherwise tie in ORDER BY created_at.
     * Product 0 is the oldest, product $count - 1 the newest.
     */
    private function createProducts(int $count, array $categories): void
    {
        for ($i = 0; $i < $count; $i++) {
            $product = Product::create([
                'category_id'   => $categories[$i % count($categories)]->id,
                'sku'           => 'HGP-' . $i . '-' . uniqid(),
                'name'          => "Goodie {$i}",
                'slug'          => "goodie-{$i}-" . uniqid(),
                'selling_price' => 100,
                'status'        => 'active',
                'is_active'     => true,
            ]);

            Product::whereKey($product->id)->update(['created_at' => now()->subMinutes(100 - $i)]);
        }
    }

    private function featuredNames($response): array
    {
        return $response->viewData('featured')->pluck('name')->all();
    }

    public function test_latest_goodies_shows_eight_products(): void
    {
        $categories = Category::factory()->count(5)->create();
        $this->createProducts(35, $categories->all());

        $response = $this->get(route('shop.home'))->assertOk();

        $this->assertCount(8, $response->viewData('featured'));
    }

    public function test_latest_goodies_spreads_products_across_categories(): void
    {
        $categories = Category::factory()->count(4)->create();
        $this->createProducts(40, $categories->all());

        $response = $this->get(route('shop.home'))->assertOk();

        $featured = $response->viewData('featured');
        $this->assertCount(8, $featured);
        $this->assertCount(4, $featured->pluck('category_id')->unique(), 'All 4 categories should be represented.');
    }

    public function test_latest_goodies_only_uses_the_newest_30_products(): void
    {
        $categories = Category::factory()->count(4)->create();
        $this->createProducts(40, $categories->all());

        $names = $this->featuredNames($this->get(route('shop.home'))->assertOk());

        // Products 0-9 are the 10 oldest, outside the 30-product pool.
        foreach (range(0, 9) as $i) {
            $this->assertNotContains("Goodie {$i}", $names, "Oldest product Goodie {$i} must not be featured.");
        }
    }

    public function test_latest_goodies_shows_all_products_when_fewer_than_eight(): void
    {
        $categories = Category::factory()->count(3)->create();
        $this->createProducts(5, $categories->all());

        $response = $this->get(route('shop.home'))->assertOk();

        $this->assertCount(5, $response->viewData('featured'));
    }
}
