<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Tests\TestCase;

class AdminDealVariantPickerTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function product(string $name, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name'           => $name,
            'slug'           => str($name)->slug('-')->toString().'-'.uniqid(),
            'sku'            => 'DVP-'.uniqid(),
            'selling_price'  => 1000,
            'status'         => 'active',
            'is_active'      => true,
            'stock_quantity' => 5,
        ], $overrides));
    }

    private function variant(Product $product, string $name, float $price): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id'    => $product->id,
            'sku'           => 'DVP-V-'.uniqid(),
            'name'          => $name,
            'selling_price' => $price,
            'discount'      => 0,
            'is_active'     => true,
        ]);

        Inventory::create([
            'product_id'         => $product->id,
            'product_variant_id' => $variant->id,
            'quantity'           => 10,
            'reorder_level'      => 5,
        ]);

        return $variant;
    }

    private function markPurchased(Product $product): void
    {
        $order = Order::create([
            'reference'        => 'ORD-DVP-'.uniqid(),
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
            'title'          => 'Variant Deal '.uniqid(),
            'slug'           => 'variant-deal-'.uniqid(),
            'discount_type'  => 'percentage',
            'discount_value' => 10,
            'status'         => 'active',
            'starts_at'      => now()->subDay(),
            'ends_at'        => now()->addDays(30),
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title'          => 'Variant Targeted Deal',
            'discount_type'  => 'percentage',
            'discount_value' => 15,
            'starts_at'      => now()->subDay()->format('Y-m-d\TH:i'),
            'ends_at'        => now()->addDays(7)->format('Y-m-d\TH:i'),
        ], $overrides);
    }

    public function test_create_form_renders_variant_picker_matching_coupon_design(): void
    {
        $product = $this->product('Picker Widget');
        $this->markPurchased($product);
        $this->variant($product, 'Small', 950);

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.deals.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Target Products & Variants', $html);
        $this->assertStringContainsString('id="dealProductSearch"', $html);
        $this->assertStringContainsString('name="variant_ids[]"', $html);
        $this->assertStringContainsString('deal-variant-check', $html);
        $this->assertStringContainsString('Small - ₦950.00', $html);
        $this->assertStringContainsString(
            'Tip: a checked product applies the deal to all its variants. Check individual variants to scope more narrowly.',
            $html
        );
    }

    public function test_variant_only_deal_is_created_and_variants_are_synced(): void
    {
        $product = $this->product('Scope Widget');
        $variant = $this->variant($product, 'Large', 1500);

        $response = $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.deals.store'), $this->payload(['variant_ids' => [$variant->id]]));

        $deal = Deal::where('title', 'Variant Targeted Deal')->firstOrFail();

        $response->assertRedirect(route('admin.deals.show', $deal));
        $this->assertEqualsCanonicalizing([$variant->id], $deal->variants->pluck('id')->all());
        $this->assertSame([], $deal->products->pluck('id')->all());
    }

    public function test_store_requires_at_least_one_product_or_variant(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->from(route('admin.deals.create'))
            ->post(route('admin.deals.store'), $this->payload())
            ->assertRedirect(route('admin.deals.create'))
            ->assertSessionHasErrors('product_ids', 'Select at least one product or variant for the deal.');

        $this->assertDatabaseCount('deals', 0);
    }

    public function test_store_syncs_products_and_variants_together(): void
    {
        $product = $this->product('Both Widget');
        $variant = $this->variant($product, 'Medium', 1200);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.deals.store'), $this->payload([
                'product_ids' => [$product->id],
                'variant_ids' => [$variant->id],
            ]));

        $deal = Deal::where('title', 'Variant Targeted Deal')->firstOrFail();

        $this->assertEqualsCanonicalizing([$product->id], $deal->products->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$variant->id], $deal->variants->pluck('id')->all());
    }

    public function test_edit_form_checks_previously_selected_variants(): void
    {
        $ineligible = $this->product('Ineligible Widget', ['stock_quantity' => 0]);
        $variant = $this->variant($ineligible, 'XL', 2000);

        $eligible = $this->product('Eligible Widget');
        $this->markPurchased($eligible);

        $deal = $this->deal();
        $deal->products()->attach($eligible->id);
        $deal->variants()->attach($variant->id);

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.deals.edit', $deal))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ineligible Widget', $html);
        $this->assertMatchesRegularExpression('/id="dv-'.$variant->id.'"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/id="dp-'.$eligible->id.'"\s+checked/', $html);
    }

    public function test_update_replaces_product_and_variant_selection(): void
    {
        $first = $this->product('First Widget');
        $firstVariant = $this->variant($first, 'A', 1100);
        $second = $this->product('Second Widget');
        $secondVariant = $this->variant($second, 'B', 1300);

        $deal = $this->deal();
        $deal->products()->attach($first->id);
        $deal->variants()->attach($firstVariant->id);

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.deals.update', $deal), $this->payload([
                'title'       => $deal->title,
                'product_ids' => [$second->id],
                'variant_ids' => [$secondVariant->id],
            ]))
            ->assertRedirect(route('admin.deals.show', $deal));

        $deal->refresh();

        $this->assertEqualsCanonicalizing([$second->id], $deal->products->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$secondVariant->id], $deal->variants->pluck('id')->all());
    }
}
