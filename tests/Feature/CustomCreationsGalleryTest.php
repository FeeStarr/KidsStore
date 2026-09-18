<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomCreationsGalleryTest extends TestCase
{
    use RefreshDatabase;

    private function createShowcase(array $overrides = []): CustomOrder
    {
        return CustomOrder::factory()->completed()->create(array_merge([
            'showcase_enabled' => true,
            'showcase_title' => 'Birthday Princess Frock',
            'showcase_price' => 35000,
            'showcase_description' => 'A beautiful custom birthday dress.',
            'showcase_image_path' => 'custom-creations/1/showcase.jpg',
            'showcase_category' => 'birthday',
        ], $overrides));
    }

    // ── Public Gallery Index ─────────────────────────────────────

    public function test_guest_can_view_gallery_without_login(): void
    {
        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('Custom Creations');
    }

    public function test_guest_sees_empty_state_when_no_showcases(): void
    {
        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('No creations to display yet');
    }

    public function test_gallery_displays_showcased_orders(): void
    {
        $order = $this->createShowcase();

        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('Birthday Princess Frock')
            ->assertSee('35,000');
    }

    public function test_gallery_does_not_show_disabled_showcases(): void
    {
        $this->createShowcase(['showcase_enabled' => false]);

        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertDontSee('Birthday Princess Frock');
    }

    public function test_gallery_does_not_show_orders_without_image(): void
    {
        $this->createShowcase(['showcase_image_path' => null]);

        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('No creations to display yet');
    }

    public function test_gallery_does_not_expose_customer_data(): void
    {
        $order = $this->createShowcase();
        $user = $order->user;

        $response = $this->get(route('shop.custom-creations.index'));
        $response->assertOk();
        $response->assertDontSee($user->name);
        $response->assertDontSee($user->email);
        $response->assertDontSee($order->child_name);
        $response->assertDontSee($order->customer_notes);
        $response->assertDontSee($order->delivery_address);
        $response->assertDontSee($order->admin_notes);
    }

    public function test_gallery_has_pagination(): void
    {
        CustomOrder::factory()->completed()->count(15)->create([
            'showcase_enabled' => true,
            'showcase_image_path' => 'custom-creations/{id}/showcase.jpg',
        ]);

        $response = $this->get(route('shop.custom-creations.index'));
        $response->assertOk();

        $view = $response->getOriginalContent();
        $creations = $view->getData()['creations'];
        $this->assertGreaterThan(1, $creations->lastPage());
    }

    // ── Category Filtering ───────────────────────────────────────

    public function test_category_filter_works(): void
    {
        $this->createShowcase(['showcase_category' => 'birthday']);
        $this->createShowcase(['showcase_title' => 'Elegant Red Gown', 'showcase_category' => 'party']);

        $response = $this->get(route('shop.custom-creations.index', ['category' => 'birthday']));
        $response->assertOk();
        $response->assertSee('Birthday Princess Frock');
        $response->assertDontSee('Elegant Red Gown');
    }

    public function test_invalid_category_shows_all(): void
    {
        $this->createShowcase();

        $this->get(route('shop.custom-creations.index', ['category' => 'invalid']))
            ->assertOk()
            ->assertSee('Birthday Princess Frock');
    }

    // ── Detail Page ──────────────────────────────────────────────

    public function test_guest_can_view_detail_page(): void
    {
        $order = $this->createShowcase();

        $this->get(route('shop.custom-creations.show', $order->id))
            ->assertOk()
            ->assertSee('Birthday Princess Frock')
            ->assertSee('35,000')
            ->assertSee('A beautiful custom birthday dress');
    }

    public function test_detail_page_returns_404_for_disabled_showcase(): void
    {
        $order = $this->createShowcase(['showcase_enabled' => false]);

        $this->get(route('shop.custom-creations.show', $order->id))
            ->assertNotFound();
    }

    public function test_detail_page_returns_404_for_no_image(): void
    {
        $order = $this->createShowcase(['showcase_image_path' => null]);

        $this->get(route('shop.custom-creations.show', $order->id))
            ->assertNotFound();
    }

    public function test_detail_page_does_not_expose_customer_data(): void
    {
        $order = $this->createShowcase();
        $user = $order->user;

        $response = $this->get(route('shop.custom-creations.show', $order->id));
        $response->assertOk();
        $response->assertDontSee($user->name);
        $response->assertDontSee($user->email);
        $response->assertDontSee($order->child_name);
        $response->assertDontSee($order->customer_notes);
    }

    public function test_manipulating_url_cannot_reveal_private_order(): void
    {
        $order = CustomOrder::factory()->submitted()->create([
            'showcase_enabled' => false,
            'child_name' => 'Secret Child',
        ]);

        $this->get(route('shop.custom-creations.show', $order->id))
            ->assertNotFound();
    }

    // ── Navigation ───────────────────────────────────────────────

    public function test_nav_link_appears_in_layout(): void
    {
        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('Custom Creations');
    }

    // ── Admin Showcase Toggle ────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_admin_can_enable_showcase(): void
    {
        $admin = $this->admin();
        $order = CustomOrder::factory()->completed()->create();

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.custom-orders.update-showcase', $order), [
                'showcase_enabled' => true,
                'showcase_title' => 'Beautiful Dress',
                'showcase_price' => 25000,
                'showcase_category' => 'party',
            ]);

        $order->refresh();
        $this->assertTrue($order->showcase_enabled);
        $this->assertEquals('Beautiful Dress', $order->showcase_title);
        $this->assertEquals(25000, $order->showcase_price);
        $this->assertEquals('party', $order->showcase_category);
    }

    public function test_admin_can_disable_showcase(): void
    {
        $admin = $this->admin();
        $order = $this->createShowcase();

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.custom-orders.update-showcase', $order), [
                'showcase_enabled' => false,
                'showcase_title' => $order->showcase_title,
            ]);

        $order->refresh();
        $this->assertFalse($order->showcase_enabled);
    }

    public function test_disabled_showcase_disappears_from_gallery(): void
    {
        $order = $this->createShowcase();

        // Initially visible
        $this->get(route('shop.custom-creations.index'))
            ->assertSee('Birthday Princess Frock');

        // Admin disables it
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')
            ->patch(route('admin.custom-orders.update-showcase', $order), [
                'showcase_enabled' => false,
                'showcase_title' => $order->showcase_title,
            ]);

        // No longer visible
        $this->get(route('shop.custom-creations.index'))
            ->assertDontSee('Birthday Princess Frock');
    }

    public function test_admin_can_upload_showcase_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $order = CustomOrder::factory()->completed()->create();

        $file = \Illuminate\Http\UploadedFile::fake()->image('showcase.jpg', 600, 800);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-orders.showcase-image', $order), [
                'showcase_image' => $file,
            ]);

        $order->refresh();
        $this->assertNotNull($order->showcase_image_path);
        $this->assertStringContainsString('custom-creations/' . $order->id, $order->showcase_image_path);
    }

    public function test_unauthenticated_cannot_enable_showcase(): void
    {
        $order = CustomOrder::factory()->completed()->create();

        $this->patch(route('admin.custom-orders.update-showcase', $order), [
            'showcase_enabled' => true,
        ])->assertRedirect();

        $order->refresh();
        $this->assertFalse($order->showcase_enabled);
    }

    public function test_customer_cannot_enable_showcase(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $order = CustomOrder::factory()->completed()->create();

        $this->actingAs($customer)
            ->patch(route('admin.custom-orders.update-showcase', $order), [
                'showcase_enabled' => true,
            ]);

        $order->refresh();
        $this->assertFalse($order->showcase_enabled);
    }
}
