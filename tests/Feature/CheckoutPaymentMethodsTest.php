<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutPaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_shows_active_payment_methods_and_help_text()
    {
        // Create user and authenticate
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a product and a variant
        $product = Product::create([
            'sku' => 'TESTSKU1',
            'name' => 'Test Product',
            'slug' => 'test-product',
            'selling_price' => 100,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TESTSKU1-V1',
            'name' => 'Default',
            'selling_price' => 100,
            'discount' => 0,
        ]);

        // Add variant to cart via service (session-backed)
        $this->app->make(\App\Services\CartService::class)->add($variant->id, 1);

        // Ensure payment method 'transfer' is active
        PaymentMethod::query()->create(['key' => 'transfer', 'label' => 'Bank transfer', 'is_active' => true]);

        $response = $this->get(route('shop.checkout.show'));

        $response->assertStatus(200);
        $response->assertSee('Bank transfer');
        $response->assertSee('Pay on delivery is by bank transfer');
    }
}
