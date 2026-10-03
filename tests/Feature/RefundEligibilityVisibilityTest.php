<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class RefundEligibilityVisibilityTest extends TestCase
{
    private function deliveredOrderFor(User $customer): Order
    {
        return Order::create([
            'reference'        => 'ORD-REFVIZ-' . uniqid(),
            'order_date'       => now()->toDateString(),
            'customer_id'      => $customer->id,
            'status'           => 'delivered',
            'delivery_method'  => 'delivery',
            'delivery_address' => '12 Test Street, Lagos',
            'total_amount'     => 1000,
            'grand_total'      => 1000,
            'amount_paid'      => 1000,
        ]);
    }

    private function addItem(Order $order, array $productAttributes): void
    {
        $product = Product::create(array_merge([
            'selling_price' => 100,
        ], $productAttributes));

        OrderItem::create([
            'order_id'            => $order->id,
            'product_id'          => $product->id,
            'quantity'            => 1,
            'unit_price'          => 100,
            'original_unit_price' => 100,
            'discount'            => 0,
            'line_total'          => 100,
        ]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER]);
    }

    public function test_order_with_no_returnable_items_greys_out_refund_button(): void
    {
        $customer = $this->customer();
        $order = $this->deliveredOrderFor($customer);
        $this->addItem($order, [
            'sku'           => 'NR-' . uniqid(),
            'name'          => 'Non Returnable Widget',
            'slug'          => 'non-returnable-widget-' . uniqid(),
            'is_returnable' => false,
        ]);

        $this->actingAs($customer)
            ->get(route('shop.account.orders.show', $order))
            ->assertOk()
            ->assertSee('Return Request')
            ->assertSee("This order can't be returned - none of its items are returnable.")
            ->assertSee('Non-returnable')
            ->assertSee('<button class="btn btn-sm btn-outline-warning" type="button" disabled', false)
            ->assertDontSee('data-bs-target="#refund-form"', false)
            ->assertDontSee('What would you like to refund?')
            ->assertDontSee('None of the items in this order are eligible');
    }

    public function test_order_not_yet_delivered_hides_return_request_section(): void
    {
        $customer = $this->customer();
        $order = Order::create([
            'reference'        => 'ORD-REFVIZ-' . uniqid(),
            'order_date'       => now()->toDateString(),
            'customer_id'      => $customer->id,
            'status'           => 'confirmed',
            'delivery_method'  => 'delivery',
            'delivery_address' => '12 Test Street, Lagos',
            'total_amount'     => 1000,
            'grand_total'      => 1000,
            'amount_paid'      => 1000,
        ]);
        $this->addItem($order, [
            'sku'  => 'RET-' . uniqid(),
            'name' => 'Returnable Toy',
            'slug' => 'returnable-toy-' . uniqid(),
        ]);

        $this->actingAs($customer)
            ->get(route('shop.account.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Return Request')
            ->assertDontSee('Request a Refund')
            ->assertDontSee('data-bs-target="#refund-form"', false)
            ->assertDontSee('What would you like to refund?');
    }

    public function test_order_outside_return_window_can_still_request_return(): void
    {
        $customer = $this->customer();
        $order = $this->deliveredOrderFor($customer);
        $this->addItem($order, [
            'sku'  => 'RET-' . uniqid(),
            'name' => 'Returnable Toy',
            'slug' => 'returnable-toy-' . uniqid(),
        ]);
        Order::whereKey($order->id)->update(['updated_at' => now()->subDays(60)]);

        $this->actingAs($customer)
            ->get(route('shop.account.orders.show', $order->fresh()))
            ->assertOk()
            ->assertSee('Request a Refund')
            ->assertSee('data-bs-target="#refund-form"', false)
            ->assertDontSee('<button class="btn btn-sm btn-outline-warning" type="button" disabled', false);
    }

    public function test_order_with_open_return_request_greys_out_refund_button(): void
    {
        $customer = $this->customer();
        $order = $this->deliveredOrderFor($customer);
        $this->addItem($order, [
            'sku'  => 'RET-' . uniqid(),
            'name' => 'Returnable Toy',
            'slug' => 'returnable-toy-' . uniqid(),
        ]);
        \App\Models\RefundRequest::create([
            'order_id'      => $order->id,
            'order_item_id' => $order->items()->first()->id,
            'status'        => 'requested',
            'reason'        => 'damaged',
            'details'       => 'Item arrived damaged.',
            'quantity'      => 1,
            'amount'        => 100,
        ]);

        $this->actingAs($customer)
            ->get(route('shop.account.orders.show', $order))
            ->assertOk()
            ->assertSee('Return Request')
            ->assertSee('All returnable items already have a return request.')
            ->assertSee('<button class="btn btn-sm btn-outline-warning" type="button" disabled', false)
            ->assertDontSee('data-bs-target="#refund-form"', false);
    }

    public function test_mixed_order_shows_refund_feature_with_disabled_non_returnable_item(): void
    {
        $customer = $this->customer();
        $order = $this->deliveredOrderFor($customer);
        $this->addItem($order, [
            'sku'           => 'NR-' . uniqid(),
            'name'          => 'Sealed Software Box',
            'slug'          => 'sealed-software-' . uniqid(),
            'is_returnable' => false,
        ]);
        $this->addItem($order, [
            'sku'  => 'RET-' . uniqid(),
            'name' => 'Returnable Dress',
            'slug' => 'returnable-dress-' . uniqid(),
        ]);

        $this->actingAs($customer)
            ->get(route('shop.account.orders.show', $order))
            ->assertOk()
            ->assertSee('Return Request')
            ->assertSee('Request a Refund')
            ->assertSee('Non-returnable')
            ->assertSee('What would you like to refund?');
    }

    public function test_delivered_order_with_returnable_items_shows_refund_feature(): void
    {
        $customer = $this->customer();
        $order = $this->deliveredOrderFor($customer);
        $this->addItem($order, [
            'sku'  => 'RET-' . uniqid(),
            'name' => 'Returnable Toy',
            'slug' => 'returnable-toy-' . uniqid(),
        ]);

        $this->actingAs($customer)
            ->get(route('shop.account.orders.show', $order))
            ->assertOk()
            ->assertSee('Return Request')
            ->assertSee('Request a Refund');
    }
}
