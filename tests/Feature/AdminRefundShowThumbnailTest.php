<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\User;
use Tests\TestCase;

class AdminRefundShowThumbnailTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function refundRequestFor(array $productAttributes): RefundRequest
    {
        $order = Order::create([
            'reference' => 'ORD-RFTHUMB-' . uniqid(),
            'order_date' => now()->toDateString(),
            'status' => 'delivered',
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street, Lagos',
            'total_amount' => 1000,
            'grand_total' => 1000,
            'amount_paid' => 1000,
        ]);
        $product = Product::create(array_merge(['selling_price' => 100], $productAttributes));
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'original_unit_price' => 100,
            'discount' => 0,
            'line_total' => 100,
        ]);

        return RefundRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'status' => 'requested',
            'reason' => 'damaged',
            'details' => 'Item was damaged.',
            'quantity' => 1,
            'amount' => 100,
        ]);
    }

    public function test_refund_show_displays_product_thumbnail(): void
    {
        $rr = $this->refundRequestFor([
            'sku' => 'RFTHUMB1',
            'name' => 'Thumbed Product',
            'slug' => 'thumbed-product',
            'image' => 'images/products/rf-thumb.jpg',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.refunds.show', $rr))
            ->assertOk()
            ->assertSee('images/products/rf-thumb.jpg')
            ->assertSee('Thumbed Product');
    }

    public function test_refund_show_shows_placeholder_without_image(): void
    {
        $rr = $this->refundRequestFor([
            'sku' => 'RFNOIMG1',
            'name' => 'Imageless Product',
            'slug' => 'imageless-product',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.refunds.show', $rr))
            ->assertOk()
            ->assertSee('bi-image')
            ->assertDontSee('images/products/');
    }
}
