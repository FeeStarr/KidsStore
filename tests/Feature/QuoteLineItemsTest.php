<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderQuote;
use App\Models\User;
use App\Services\CustomQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuoteLineItemsTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    private function orderFor(User $customer, string $status = CustomOrder::STATUS_QUOTE_PENDING): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => 'CO-LINES-' . uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => $status,
            'child_name' => 'Amara',
            'delivery_method' => 'pickup',
            'return_policy_acknowledged' => true,
        ]);
    }

    private function createQuote(CustomOrder $order, User $admin): CustomOrderQuote
    {
        Notification::fake();

        return app(CustomQuoteService::class)->create($order, [
            'base_price' => 12000,
            'fabric_cost' => 3500,
            'customization_cost' => 500,
            'embellishment_cost' => 2000,
            'measurement_fee' => 1000,
            'rush_fee' => 0,
            'delivery_fee' => 0,
            'discount' => 500,
        ], 18500, null, 7, $admin->id);
    }

    public function test_new_line_item_labels_are_stored(): void
    {
        $admin = $this->admin();
        $quote = $this->createQuote($this->orderFor($this->customer()), $admin);

        $labels = array_column($quote->breakdown, 'label');

        $this->assertSame([
            'Frock — base garment',
            'Fabric — selected fabric',
            'Design & Customization — modifications/special design requests',
            'Embellishments — decorative additions',
            'Custom Measurements — made to the customer\'s measurements',
            'Discount',
        ], $labels);
    }

    public function test_new_labels_render_on_customer_and_admin_pages(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->orderFor($customer);
        $this->createQuote($order, $admin);

        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('Frock')
            ->assertSee('base garment')
            ->assertSee('Design & Customization')
            ->assertSee('modifications/special design requests')
            ->assertSee('Embellishments')
            ->assertSee('decorative additions')
            ->assertSee('Custom Measurements')
            ->assertSee('made to the customer');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.custom-orders.show', $order))
            ->assertOk()
            ->assertSee('Frock')
            ->assertSee('base garment')
            ->assertSee('Design & Customization')
            ->assertSee('made to the customer');
    }

    public function test_admin_quote_form_uses_matching_field_labels(): void
    {
        $admin = $this->admin();
        $order = $this->orderFor($this->customer());

        $this->actingAs($admin, 'admin')
            ->get(route('admin.custom-orders.show', $order))
            ->assertOk()
            ->assertSee('Frock &mdash; base garment', false)
            ->assertSee('Fabric &mdash; selected fabric', false)
            ->assertSee('Design &amp; Customization &mdash; modifications/special design requests', false)
            ->assertSee('Embellishments &mdash; decorative additions', false)
            ->assertSee('Custom Measurements &mdash; made to the customer\'s measurements', false);
    }

    public function test_legacy_breakdown_labels_still_render(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->orderFor($customer, CustomOrder::STATUS_QUOTED);

        CustomOrderQuote::create([
            'custom_order_id' => $order->id,
            'version' => 1,
            'base_price' => 5000,
            'total' => 5000,
            'breakdown' => [
                ['label' => 'Base Frock', 'amount' => 5000],
            ],
            'valid_until' => now()->addDays(7),
            'status' => CustomOrderQuote::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('Base Frock');
    }
}
