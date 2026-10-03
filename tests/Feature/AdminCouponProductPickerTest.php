<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Tests\TestCase;

class AdminCouponProductPickerTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function product(string $name): Product
    {
        return Product::create([
            'name'          => $name,
            'slug'          => str($name)->slug('-')->toString() . '-' . uniqid(),
            'sku'           => 'CP-' . uniqid(),
            'selling_price' => 1000,
            'status'        => 'active',
            'is_active'     => true,
        ]);
    }

    public function test_coupon_form_shows_a_thumbnail_for_each_product(): void
    {
        $imaged = $this->product('Imaged Coupon Product');
        ProductImage::create([
            'product_id' => $imaged->id,
            'path'       => 'products/coupon-thumb.jpg',
            'is_primary' => true,
        ]);
        $this->product('Plain Coupon Product');

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.coupons.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('storage/products/coupon-thumb.jpg', $html);
        $this->assertStringContainsString('style="width:44px;height:44px;object-fit:cover;"', $html);
        $this->assertStringContainsString('<i class="bi bi-image"></i>', $html);
    }

    public function test_coupon_form_variant_rows_are_not_given_thumbnails(): void
    {
        $product = $this->product('Variant Coupon Product');
        ProductVariant::create([
            'product_id'    => $product->id,
            'sku'           => 'VCP-' . uniqid(),
            'name'          => 'Large',
            'selling_price' => 1500,
            'is_active'     => true,
        ]);

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.coupons.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<div class="form-check ms-3 mt-1">.*?<\/div>/s', $html);

        preg_match('/<div class="form-check ms-3 mt-1">.*?<\/div>/s', $html, $variantRow);

        $this->assertStringContainsString('Large', $variantRow[0]);
        $this->assertStringNotContainsString('<img', $variantRow[0]);
    }
}
