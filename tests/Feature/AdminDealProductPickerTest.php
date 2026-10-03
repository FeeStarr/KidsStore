<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Tests\TestCase;

class AdminDealProductPickerTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function product(string $name, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name'           => $name,
            'slug'           => str($name)->slug('-')->toString() . '-' . uniqid(),
            'sku'            => 'DP-' . uniqid(),
            'selling_price'  => 1000,
            'status'         => 'active',
            'is_active'      => true,
            'stock_quantity' => 5,
        ], $overrides));
    }

    private function markPurchased(Product $product): void
    {
        $order = Order::create([
            'reference'        => 'ORD-DP-' . uniqid(),
            'order_date'       => now()->toDateString(),
            'customer_id'      => $this->makeCustomer()->id,
            'status'           => 'delivered',
            'delivery_method'  => 'delivery',
            'delivery_address' => '12 Test Street, Lagos',
            'total_amount'     => 1000,
            'grand_total'      => 1000,
            'amount_paid'      => 1000,
        ]);

        OrderItem::create([
            'order_id'            => $order->id,
            'product_id'          => $product->id,
            'quantity'            => 1,
            'unit_price'          => 1000,
            'original_unit_price' => 1000,
            'discount'            => 0,
            'line_total'          => 1000,
        ]);
    }

    private function deal(array $overrides = []): Deal
    {
        return Deal::create(array_merge([
            'title'          => 'Picker Deal ' . uniqid(),
            'slug'           => 'picker-deal-' . uniqid(),
            'discount_type'  => 'percentage',
            'discount_value' => 10,
            'status'         => 'active',
            'starts_at'      => now()->subDay(),
            'ends_at'        => now()->addDays(30),
        ], $overrides));
    }

    public function test_deal_product_picker_only_lists_purchased_and_in_stock_products(): void
    {
        $bought  = $this->product('Bought Widget');
        $this->markPurchased($bought);

        $this->product('Never Bought Widget');

        $stockOut = $this->product('Stockout Widget', ['stock_quantity' => 0]);
        $this->markPurchased($stockOut);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.deals.create'))
            ->assertOk()
            ->assertSee('Bought Widget')
            ->assertDontSee('Never Bought Widget')
            ->assertDontSee('Stockout Widget');
    }

    public function test_deal_product_picker_shows_thumbnails_with_placeholder_fallback(): void
    {
        $imaged = $this->product('Imaged Widget');
        $this->markPurchased($imaged);
        ProductImage::create([
            'product_id' => $imaged->id,
            'path'       => 'products/deal-thumb.jpg',
            'is_primary' => true,
        ]);

        $plain = $this->product('Plain Widget');
        $this->markPurchased($plain);

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.deals.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('storage/products/deal-thumb.jpg', $html);
        $this->assertStringContainsString('<i class="bi bi-image"></i>', $html);
        $this->assertStringContainsString('style="width:44px;height:44px;object-fit:cover;"', $html);
    }

    public function test_deal_edit_picker_keeps_products_already_attached_to_the_deal(): void
    {
        $eligible = $this->product('Eligible Deal Widget');
        $this->markPurchased($eligible);

        $legacy = $this->product('Legacy Deal Widget');
        $absent = $this->product('Absent Deal Widget');

        $deal = $this->deal();
        $deal->products()->attach($legacy->id);

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.deals.edit', $deal))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Legacy Deal Widget', $html);
        $this->assertMatchesRegularExpression('/id="dp-' . $legacy->id . '"\s+checked/', $html);
        $this->assertStringContainsString('Eligible Deal Widget', $html);
        $this->assertStringNotContainsString('Absent Deal Widget', $html);
    }
}
