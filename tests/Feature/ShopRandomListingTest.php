<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopRandomListingTest extends TestCase
{
    private function makeProducts(int $count): void
    {
        $category = Category::factory()->create();

        for ($i = 1; $i <= $count; $i++) {
            Product::create([
                'category_id' => $category->id,
                'sku' => 'RND-' . $i . '-' . uniqid(),
                'name' => "Random Product {$i}",
                'slug' => "random-product-{$i}-" . uniqid(),
                'selling_price' => 100 + $i,
                'status' => 'active',
            ]);
        }
    }

    private function randomOrderQueries(callable $request): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $request();

        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn ($q) => str_contains(strtoupper($q), 'RAND('))
            ->values();

        DB::disableQueryLog();

        return $queries->all();
    }

    private function randomOrderSeeds(callable $request): array
    {
        return array_map(
            fn ($q) => preg_match('/RAND\(\d+\)/', $q, $m) ? $m[0] : null,
            $this->randomOrderQueries($request)
        );
    }

    public function test_default_shop_listing_is_ordered_by_seeded_random(): void
    {
        $this->makeProducts(3);

        $queries = $this->randomOrderQueries(fn () => $this->get('/shop')->assertOk());

        $this->assertNotEmpty($queries, 'Expected the default shop listing to use RAND() ordering.');
        $this->assertMatchesRegularExpression('/RAND\(\d+\)/', $queries[0]);
    }

    public function test_random_seed_is_stable_across_requests(): void
    {
        $this->makeProducts(3);

        $first  = $this->randomOrderSeeds(fn () => $this->get('/shop')->assertOk());
        $second = $this->randomOrderSeeds(fn () => $this->get('/shop?page=2')->assertOk());

        $this->assertNotEmpty($first);
        $this->assertNotEmpty($second);
        $this->assertMatchesRegularExpression('/^RAND\(\d+\)$/', $first[0]);
        $this->assertSame($first[0], $second[0], 'Shuffle seed changed between pages - pagination order must stay stable.');
    }

    public function test_explicit_sorts_override_random_order(): void
    {
        $this->makeProducts(3);

        $queries = $this->randomOrderQueries(fn () => $this->get('/shop?sort=newest')->assertOk());
        $this->assertEmpty($queries, 'Explicit sort must not use RAND() ordering.');

        $this->get('/shop?sort=name')
            ->assertOk()
            ->assertSeeInOrder(['Random Product 1', 'Random Product 2', 'Random Product 3']);
    }

    public function test_sort_dropdown_offers_random_as_default_and_keeps_newest(): void
    {
        $this->makeProducts(1);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('<option value="" selected>Random</option>', false)
            ->assertSee('<option value="newest"', false);
    }
}
