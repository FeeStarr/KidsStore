<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePriceEditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function makeProduct(float $price = 500): array
    {
        $product = Product::create([
            'sku' => 'PEDIT' . uniqid(),
            'name' => 'Editable Product',
            'slug' => 'editable-product-' . uniqid(),
            'selling_price' => $price,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VEDIT' . uniqid(),
            'name' => 'Default',
            'selling_price' => $price,
        ]);

        return [$product, $variant];
    }

    private function makePurchase(Product $product, ProductVariant $variant, string $status = 'received', int $qty = 10, float $sell = 500): Purchase
    {
        return app(PurchaseService::class)->create([
            'purchase_number' => 'PO-' . uniqid(),
            'purchase_date' => now(),
            'status' => $status,
            'items' => [[
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => $qty,
                'cost_price' => 100,
                'shipping_fee' => 10,
                'packaging_cost' => 5,
                'other_costs' => 5,
                'discount' => 10,
                'selling_price' => $sell,
            ]],
        ]);
    }

    private function postPrices(Purchase $purchase, array $row): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin(), 'admin')->put(
            route('admin.purchases.update-prices', $purchase),
            ['items' => [$row]]
        );
    }

    public function test_editor_shows_inputs_for_received_purchase(): void
    {
        [$product, $variant] = $this->makeProduct();
        $purchase = $this->makePurchase($product, $variant);

        $response = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.purchases.edit-prices', $purchase));

        $response->assertOk();
        $response->assertSee('items[' . $purchase->items->first()->id . '][quantity]', false);
        $response->assertSee('items[' . $purchase->items->first()->id . '][selling_price]', false);
        $response->assertSee('Save changes');
    }

    public function test_editor_redirects_for_pending_purchase(): void
    {
        [$product, $variant] = $this->makeProduct();
        $purchase = $this->makePurchase($product, $variant, 'pending');

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.purchases.edit-prices', $purchase))
            ->assertRedirect(route('admin.purchases.show', $purchase));
    }

    public function test_saving_updates_price_and_pushes_to_product(): void
    {
        [$product, $variant] = $this->makeProduct();
        $purchase = $this->makePurchase($product, $variant);
        $item = $purchase->items->first();
        $movements = InventoryMovement::count();

        $this->postPrices($purchase, [
            'id' => $item->id,
            'quantity' => 10,
            'selling_price' => 600,
        ])->assertRedirect(route('admin.purchases.show', $purchase));

        $this->assertDatabaseHas('purchase_items', ['id' => $item->id, 'selling_price' => 600]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'selling_price' => 600]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'selling_price' => 600]);

        $this->assertEquals($movements, InventoryMovement::count(), 'No stock movement expected for unchanged qty');
        $this->assertEquals(10, (int) Inventory::where('product_variant_id', $variant->id)->value('quantity'));
    }

    public function test_qty_increase_adjusts_stock_up_and_recalcs_totals(): void
    {
        [$product, $variant] = $this->makeProduct();
        $purchase = $this->makePurchase($product, $variant);
        $item = $purchase->items->first();

        $this->postPrices($purchase, [
            'id' => $item->id,
            'quantity' => 13,
            'selling_price' => 500,
        ])->assertRedirect(route('admin.purchases.show', $purchase));

        $this->assertEquals(13, (int) Inventory::where('product_variant_id', $variant->id)->value('quantity'));
        $this->assertEquals(13, (int) $product->fresh()->stock_quantity);

        $movement = InventoryMovement::where('product_variant_id', $variant->id)->latest('id')->first();
        $this->assertSame('purchase', $movement->type);
        $this->assertEquals(3, (int) $movement->quantity);
        $this->assertSame(Purchase::class, $movement->reference_type);
        $this->assertEquals($purchase->id, (int) $movement->reference_id);

        // unit landed = 120, less 10% discount = 108/unit
        $this->assertDatabaseHas('purchase_items', ['id' => $item->id, 'quantity' => 13, 'line_total' => 1404]);
        $this->assertEquals(1404, (float) $purchase->fresh()->grand_total);
    }

    public function test_qty_decrease_adjusts_stock_down(): void
    {
        [$product, $variant] = $this->makeProduct();
        $purchase = $this->makePurchase($product, $variant);
        $item = $purchase->items->first();

        $this->postPrices($purchase, [
            'id' => $item->id,
            'quantity' => 7,
            'selling_price' => 500,
        ])->assertRedirect(route('admin.purchases.show', $purchase));

        $this->assertEquals(7, (int) Inventory::where('product_variant_id', $variant->id)->value('quantity'));

        $movement = InventoryMovement::where('product_variant_id', $variant->id)->latest('id')->first();
        $this->assertSame('adjustment', $movement->type);
        $this->assertEquals(-3, (int) $movement->quantity);

        $this->assertDatabaseHas('purchase_items', ['id' => $item->id, 'quantity' => 7, 'line_total' => 756]);
        $this->assertEquals(756, (float) $purchase->fresh()->grand_total);
    }

    public function test_qty_decrease_beyond_stock_is_blocked(): void
    {
        [$product, $variant] = $this->makeProduct();
        $purchase = $this->makePurchase($product, $variant);
        $item = $purchase->items->first();

        // Simulate 8 units already sold: only 2 left of the 10 received.
        Inventory::where('product_variant_id', $variant->id)->update(['quantity' => 2]);
        $movements = InventoryMovement::count();

        $this->actingAs($this->admin(), 'admin')
            ->from(route('admin.purchases.edit-prices', $purchase))
            ->put(route('admin.purchases.update-prices', $purchase), [
                'items' => [['id' => $item->id, 'quantity' => 7, 'selling_price' => 500]],
            ])
            ->assertRedirect(route('admin.purchases.edit-prices', $purchase))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseHas('purchase_items', ['id' => $item->id, 'quantity' => 10]);
        $this->assertEquals(2, (int) Inventory::where('product_variant_id', $variant->id)->value('quantity'));
        $this->assertEquals($movements, InventoryMovement::count(), 'No movements should be written on rejection');
    }

    public function test_pending_purchase_cannot_be_price_edited(): void
    {
        [$product, $variant] = $this->makeProduct();
        $purchase = $this->makePurchase($product, $variant, 'pending');
        $item = $purchase->items->first();

        $this->postPrices($purchase, [
            'id' => $item->id,
            'quantity' => 99,
            'selling_price' => 999,
        ])->assertRedirect(route('admin.purchases.show', $purchase));

        $this->assertDatabaseHas('purchase_items', ['id' => $item->id, 'quantity' => 10, 'selling_price' => 500]);
    }

    public function test_zero_selling_price_is_stored_but_not_pushed(): void
    {
        [$product, $variant] = $this->makeProduct();
        $purchase = $this->makePurchase($product, $variant);
        $item = $purchase->items->first();

        $this->postPrices($purchase, [
            'id' => $item->id,
            'quantity' => 10,
            'selling_price' => 0,
        ])->assertRedirect(route('admin.purchases.show', $purchase));

        $this->assertDatabaseHas('purchase_items', ['id' => $item->id, 'selling_price' => 0]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'selling_price' => 500]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'selling_price' => 500]);
    }

    public function test_foreign_item_id_is_rejected(): void
    {
        [$productA, $variantA] = $this->makeProduct();
        $purchaseA = $this->makePurchase($productA, $variantA);
        [$productB, $variantB] = $this->makeProduct();
        $purchaseB = $this->makePurchase($productB, $variantB);
        $foreignItem = $purchaseB->items->first();

        $this->postPrices($purchaseA, [
            'id' => $foreignItem->id,
            'quantity' => 1,
            'selling_price' => 1,
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseHas('purchase_items', ['id' => $foreignItem->id, 'quantity' => 10, 'selling_price' => 500]);
    }

    public function test_requires_update_inventory_permission(): void
    {
        [$product, $variant] = $this->makeProduct();
        $purchase = $this->makePurchase($product, $variant);
        $item = $purchase->items->first();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer, 'admin')
            ->put(route('admin.purchases.update-prices', $purchase), [
                'items' => [['id' => $item->id, 'quantity' => 99, 'selling_price' => 1]],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_items', ['id' => $item->id, 'quantity' => 10]);
    }

    public function test_old_sync_endpoint_is_removed(): void
    {
        [$product, $variant] = $this->makeProduct();
        $purchase = $this->makePurchase($product, $variant);

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/purchases/' . $purchase->id . '/update-selling-prices')
            ->assertNotFound();
    }
}
