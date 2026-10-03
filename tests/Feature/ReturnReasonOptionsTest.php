<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReturnReasonOptionsTest extends TestCase
{
    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER]);
    }

    private function deliveredOrderFor(User $customer): Order
    {
        $order = Order::create([
            'reference'        => 'ORD-REASONS-' . uniqid(),
            'order_date'       => now()->toDateString(),
            'customer_id'      => $customer->id,
            'status'           => 'delivered',
            'delivery_method'  => 'delivery',
            'delivery_address' => '12 Test Street, Lagos',
            'total_amount'     => 1000,
            'grand_total'      => 1000,
            'amount_paid'      => 1000,
        ]);

        $product = Product::create([
            'selling_price' => 100,
            'sku'           => 'RSN-' . uniqid(),
            'name'          => 'Reason Test Toy',
            'slug'          => 'reason-test-toy-' . uniqid(),
        ]);

        OrderItem::create([
            'order_id'            => $order->id,
            'product_id'          => $product->id,
            'quantity'            => 1,
            'unit_price'          => 100,
            'original_unit_price' => 100,
            'discount'            => 0,
            'line_total'          => 100,
        ]);

        return $order;
    }

    public function test_refund_form_offers_the_split_reasons_and_hides_removed_ones(): void
    {
        $customer = $this->customer();
        $order    = $this->deliveredOrderFor($customer);

        $this->actingAs($customer)
            ->get(route('shop.account.orders.show', $order))
            ->assertOk()
            ->assertSee('<option value="wrong_item">Wrong item</option>', false)
            ->assertSee('<option value="wrong_color">Wrong color</option>', false)
            ->assertSee('<option value="incomplete_order">Incomplete</option>', false)
            ->assertDontSee('value="not_as_described"')
            ->assertSee('<option value="damaged">Damaged</option>', false)
            ->assertSee('<option value="missing_item">Missing item</option>', false)
            ->assertDontSee('value="wrong_size"')
            ->assertDontSee('Wrong size')
            ->assertDontSee('value="changed_mind"')
            ->assertDontSee('value="order_cancelled"');
    }

    public function test_removed_reasons_are_rejected_by_validation(): void
    {
        $customer = $this->customer();
        $order    = $this->deliveredOrderFor($customer);

        $this->actingAs($customer)
            ->from(route('shop.account.orders.show', $order))
            ->post(route('shop.refund.store', $order), [
                'scope'        => 'full',
                'reason'       => 'wrong_size',
                'details'      => 'They sent the wrong size.',
                'request_type' => 'refund',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($customer)
            ->post(route('shop.refund.store', $order), [
                'scope'        => 'full',
                'reason'       => 'order_cancelled',
                'request_type' => 'refund',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($customer)
            ->post(route('shop.refund.store', $order), [
                'scope'        => 'full',
                'reason'       => 'changed_mind',
                'request_type' => 'refund',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($customer)
            ->post(route('shop.refund.store', $order), [
                'scope'        => 'full',
                'reason'       => 'not_as_described',
                'details'      => 'The item was not as described.',
                'request_type' => 'refund',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('refund_requests', 0);
    }

    public function test_split_reason_can_be_submitted(): void
    {
        $customer = $this->customer();
        $order    = $this->deliveredOrderFor($customer);

        $this->actingAs($customer)
            ->post(route('shop.refund.store', $order), [
                'scope'        => 'full',
                'reason'       => 'wrong_color',
                'details'      => 'The dress arrived in the wrong colour.',
                'request_type' => 'refund',
                'evidence'     => UploadedFile::fake()->image('wrong-colour.jpg'),
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('refund_requests', [
            'order_id' => $order->id,
            'reason'   => 'wrong_color',
        ]);
    }
}
