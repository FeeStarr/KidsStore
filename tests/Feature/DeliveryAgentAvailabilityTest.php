<?php

namespace Tests\Feature;

use App\Models\DeliveryAgent;
use App\Models\DeliveryCharge;
use App\Models\DeliveryLocation;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryAgentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeVariant(float $price = 2000): ProductVariant
    {
        $product = Product::create([
            'sku'           => 'SKU-' . uniqid(),
            'name'          => 'Test Product',
            'slug'          => 'test-product-' . uniqid(),
            'selling_price' => $price,
            'is_active'     => true,
            'status'        => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id'    => $product->id,
            'sku'           => 'SKU-V-' . uniqid(),
            'name'          => 'Default',
            'selling_price' => $price,
            'discount'      => 0,
            'is_active'     => true,
        ]);

        Inventory::create([
            'product_id'          => $product->id,
            'product_variant_id'  => $variant->id,
            'quantity'            => 100,
            'reorder_level'       => 5,
        ]);

        return $variant;
    }

    private function makeLocation(): DeliveryLocation
    {
        return DeliveryLocation::create(['name' => 'Ikeja', 'state' => 'Lagos', 'is_active' => true]);
    }

    private function makeServiceableCharge(DeliveryLocation $location, float $amount = 1500): DeliveryCharge
    {
        $agent = DeliveryAgent::create([
            'name'           => 'Test Agent',
            'account_number' => 'AG' . rand(10000, 99999),
            'phone'          => '08012345678',
            'is_active'      => true,
        ]);

        return DeliveryCharge::create([
            'delivery_agent_id'    => $agent->id,
            'delivery_location_id' => $location->id,
            'amount'               => $amount,
            'is_active'            => true,
        ]);
    }

    private function checkoutPayload(DeliveryLocation $location): array
    {
        return [
            'delivery_method'      => 'delivery',
            'phone'                => '08012345678',
            'address'              => '12 Test Street',
            'delivery_location_id' => $location->id,
            'payment_method'       => 'pay_on_delivery',
        ];
    }

    public function test_checkout_blocks_order_when_location_has_no_agent(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant();
        $this->actingAs($user);
        app(CartService::class)->add($variant->id, 1);

        $location = $this->makeLocation();

        $this->post(route('shop.checkout.place'), $this->checkoutPayload($location))
            ->assertRedirect(route('shop.checkout.show'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(1, app(CartService::class)->count());
    }

    public function test_checkout_blocks_order_when_agent_is_inactive(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant();
        $this->actingAs($user);
        app(CartService::class)->add($variant->id, 1);

        $location = $this->makeLocation();
        $charge = $this->makeServiceableCharge($location);
        $charge->agent->update(['is_active' => false]);

        $this->post(route('shop.checkout.place'), $this->checkoutPayload($location))
            ->assertRedirect(route('shop.checkout.show'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_places_order_when_agent_is_available(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant();
        $this->actingAs($user);
        app(CartService::class)->add($variant->id, 1);

        $location = $this->makeLocation();
        $charge = $this->makeServiceableCharge($location, 2500);

        $this->post(route('shop.checkout.place'), $this->checkoutPayload($location))
            ->assertSessionHas('success');

        $order = $user->orders()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals($charge->delivery_agent_id, $order->delivery_agent_id);
        $this->assertEquals(2500, (float) $order->delivery_charge_amount);
    }
}
