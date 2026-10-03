<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class AdminProductReturnableToggleTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function product(bool $returnable = true): Product
    {
        return Product::create([
            'name'          => 'Toggleable Product',
            'slug'          => 'toggleable-product-' . uniqid(),
            'sku'           => 'TGL-' . uniqid(),
            'selling_price' => 1500,
            'status'        => 'active',
            'is_active'     => true,
            'is_returnable' => $returnable,
        ]);
    }

    public function test_products_index_shows_returnable_badge(): void
    {
        $this->admin();
        $this->product(true);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Returnable')
            ->assertSee('Disable returns');
    }

    public function test_products_index_shows_non_returnable_badge(): void
    {
        $this->product(false);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Non-returnable')
            ->assertSee('Enable returns');
    }

    public function test_toggle_returnable_endpoint_switches_the_flag(): void
    {
        $product = $this->product(true);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.products.toggle-returnable', $product))
            ->assertSessionHas('success');

        $this->assertFalse($product->fresh()->is_returnable);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.products.toggle-returnable', $product))
            ->assertSessionHas('success');

        $this->assertTrue($product->fresh()->is_returnable);
    }

    public function test_guest_cannot_toggle_returnable(): void
    {
        $product = $this->product(true);

        $this->post(route('admin.products.toggle-returnable', $product))
            ->assertRedirect(route('admin.login'));

        $this->assertTrue($product->fresh()->is_returnable);
    }
}
