<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Tests\TestCase;

class AdminProductReviewsPageTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function product(string $name, string $sku): Product
    {
        return Product::create([
            'name'          => $name,
            'slug'          => str($name)->slug('-')->toString() . '-' . uniqid(),
            'sku'           => $sku . '-' . uniqid(),
            'selling_price' => 3000,
            'status'        => 'active',
            'is_active'     => true,
        ]);
    }

    private function review(Product $product, int $rating, string $title, string $comment): ProductReview
    {
        return ProductReview::create([
            'product_id'         => $product->id,
            'customer_id'        => $this->makeCustomer()->id,
            'rating'             => $rating,
            'title'              => $title,
            'comment'            => $comment,
            'verified_purchase'  => true,
        ]);
    }

    public function test_admin_can_see_product_reviews_page_with_stats_and_content(): void
    {
        $product = $this->product('Reviewable Toy', 'REVTOY');
        $this->review($product, 5, 'Amazing quality', 'Super soft fabric and true to size.');

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.product-reviews.index'))
            ->assertOk()
            ->assertSee('Product Reviews')
            ->assertSee('Reviewable Toy')
            ->assertSee('Amazing quality')
            ->assertSee('Super soft fabric and true to size.')
            ->assertSee('Verified purchase')
            ->assertSee('Total reviews')
            ->assertSee('Average rating');
    }

    public function test_product_filter_shows_only_that_products_reviews(): void
    {
        $keep    = $this->product('Keep Me', 'KEEP');
        $hide    = $this->product('Hide Me', 'HIDE');
        $this->review($keep, 5, 'Keeper comment', 'Keeper comment body.');
        $this->review($hide, 1, 'Hidden comment', 'Hidden comment body.');

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.product-reviews.index', ['product_id' => $keep->id]))
            ->assertOk()
            ->assertSee('Keeper comment body.')
            ->assertDontSee('Hidden comment body.');
    }

    public function test_product_edit_page_links_to_its_reviews(): void
    {
        $product = $this->product('Linked Product', 'LNK');
        $this->review($product, 4, 'Nice one', 'A nice one indeed.');

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Reviews (1)')
            ->assertSee(route('admin.product-reviews.index', ['product_id' => $product->id]), false);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.product-reviews.index'))
            ->assertRedirect(route('admin.login'));
    }
}
