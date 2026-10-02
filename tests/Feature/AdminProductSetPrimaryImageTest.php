<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Tests\TestCase;

class AdminProductSetPrimaryImageTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function productWithTwoImages(): array
    {
        $product = Product::create([
            'category_id' => Category::factory()->create()->id,
            'sku' => 'SETPRIMARY-' . uniqid(),
            'name' => 'Primary Test Shirt',
            'slug' => 'primary-test-shirt',
            'selling_price' => 50,
        ]);

        $primary = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'images/products/primary.jpg',
            'is_primary' => true,
        ]);
        $other = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'images/products/other.jpg',
            'is_primary' => false,
        ]);

        return [$product, $primary, $other];
    }

    public function test_set_primary_form_is_not_nested_inside_product_form(): void
    {
        [$product, , $other] = $this->productWithTwoImages();

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->getContent();

        $mainFormOpen = strpos($html, 'action="' . route('admin.products.update', $product) . '"');
        $this->assertNotFalse($mainFormOpen, 'Main product form not found in response.');

        $mainFormClose = strpos($html, '</form>', $mainFormOpen);
        $primaryFormPos = strpos($html, 'id="primary-' . $other->id . '"');

        $this->assertNotFalse($mainFormClose, 'Main product form not closed in response.');
        $this->assertNotFalse($primaryFormPos, 'Hidden set-primary form not found in response.');
        $this->assertTrue(
            $primaryFormPos > $mainFormClose,
            'Hidden set-primary form is nested inside the product form (button form= reference would be dropped by the browser).'
        );
    }

    public function test_admin_can_set_primary_image(): void
    {
        [$product, $primary, $other] = $this->productWithTwoImages();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.products.images.primary', [$product, $other->id]))
            ->assertRedirect();

        $this->assertSame(0, (int) $primary->fresh()->is_primary);
        $this->assertSame(1, (int) $other->fresh()->is_primary);
    }
}
