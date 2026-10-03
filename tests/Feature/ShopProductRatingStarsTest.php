<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductReview;
use Tests\TestCase;

class ShopProductRatingStarsTest extends TestCase
{
    private function productWithReview(int $rating = 5): Product
    {
        $product = Product::create([
            'name'          => 'Star Rating Product',
            'slug'          => 'star-rating-product-' . uniqid(),
            'sku'           => 'STAR-' . uniqid(),
            'selling_price' => 2500,
            'status'        => 'active',
            'is_active'     => true,
        ]);

        ProductReview::create([
            'product_id'  => $product->id,
            'customer_id' => $this->makeCustomer()->id,
            'rating'      => $rating,
            'title'       => 'Wonderful',
            'comment'     => 'My kid loved it.',
        ]);

        return $product;
    }

    public function test_product_card_rating_uses_star_glyph_inside_stars_span(): void
    {
        $this->productWithReview();

        $this->get(route('shop.products.index'))
            ->assertOk()
            ->assertSee('<span class="stars">&#9733;</span>', false);
    }

    public function test_shop_star_color_is_orange_on_listing_and_detail_pages(): void
    {
        $product = $this->productWithReview();

        $this->get(route('shop.products.index'))
            ->assertOk()
            ->assertSee('.stars { color:#ff8c42; letter-spacing:1px; }', false);

        $this->get(route('shop.products.show', $product))
            ->assertOk()
            ->assertSee('.stars { color:#ff8c42; letter-spacing:1px; }', false)
            ->assertSee('class="stars"', false);
    }
}
