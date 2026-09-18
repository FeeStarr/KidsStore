<?php

namespace Tests\Feature;

use App\Models\CustomCreation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomCreationsGalleryTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────

    private function createCreation(array $overrides = []): CustomCreation
    {
        return CustomCreation::factory()->create(array_merge([
            'title' => 'Birthday Princess Frock',
            'image_path' => 'custom-creations/test-image.jpg',
            'price' => 35000,
            'is_price_from' => false,
            'description' => 'A beautiful custom birthday dress.',
            'category' => 'birthday',
            'is_active' => true,
        ], $overrides));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    // ── Public Gallery Index ─────────────────────────────────────

    public function test_guest_can_view_gallery_without_login(): void
    {
        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('Custom Creations');
    }

    public function test_guest_sees_empty_state_when_no_creations(): void
    {
        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('No creations to display yet');
    }

    public function test_gallery_displays_active_creations(): void
    {
        $creation = $this->createCreation();

        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('Birthday Princess Frock')
            ->assertSee('35,000');
    }

    public function test_gallery_does_not_show_inactive_creations(): void
    {
        $this->createCreation(['is_active' => false]);

        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertDontSee('Birthday Princess Frock');
    }

    public function test_gallery_shows_from_prefix_when_is_price_from(): void
    {
        $this->createCreation(['price' => 35000, 'is_price_from' => true]);

        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('From')
            ->assertSee('35,000');
    }

    public function test_gallery_has_pagination(): void
    {
        CustomCreation::factory()->count(15)->active()->create();

        $response = $this->get(route('shop.custom-creations.index'));
        $response->assertOk();

        $view = $response->getOriginalContent();
        $creations = $view->getData()['creations'];
        $this->assertGreaterThan(1, $creations->lastPage());
    }

    // ── Category Filtering ───────────────────────────────────────

    public function test_category_filter_works(): void
    {
        $this->createCreation(['category' => 'birthday']);
        $this->createCreation(['title' => 'Elegant Red Gown', 'category' => 'party']);

        $response = $this->get(route('shop.custom-creations.index', ['category' => 'birthday']));
        $response->assertOk();
        $response->assertSee('Birthday Princess Frock');
        $response->assertDontSee('Elegant Red Gown');
    }

    public function test_invalid_category_shows_all(): void
    {
        $this->createCreation();

        $this->get(route('shop.custom-creations.index', ['category' => 'invalid']))
            ->assertOk()
            ->assertSee('Birthday Princess Frock');
    }

    // ── Detail Page ──────────────────────────────────────────────

    public function test_guest_can_view_detail_page(): void
    {
        $creation = $this->createCreation();

        $this->get(route('shop.custom-creations.show', $creation->id))
            ->assertOk()
            ->assertSee('Birthday Princess Frock')
            ->assertSee('35,000')
            ->assertSee('A beautiful custom birthday dress');
    }

    public function test_detail_page_returns_404_for_inactive(): void
    {
        $creation = $this->createCreation(['is_active' => false]);

        $this->get(route('shop.custom-creations.show', $creation->id))
            ->assertNotFound();
    }

    public function test_detail_page_shows_from_prefix(): void
    {
        $creation = $this->createCreation(['price' => 35000, 'is_price_from' => true]);

        $this->get(route('shop.custom-creations.show', $creation->id))
            ->assertOk()
            ->assertSee('From');
    }

    public function test_detail_page_shows_category_badge(): void
    {
        $creation = $this->createCreation(['category' => 'princess']);

        $this->get(route('shop.custom-creations.show', $creation->id))
            ->assertOk()
            ->assertSee('Princess Dresses');
    }

    public function test_detail_page_shows_start_custom_order_cta(): void
    {
        $creation = $this->createCreation();

        $this->get(route('shop.custom-creations.show', $creation->id))
            ->assertOk()
            ->assertSee('Start Custom Order');
    }

    // ── Admin CRUD ───────────────────────────────────────────────

    public function test_admin_can_view_index(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.custom-creations.index'))
            ->assertOk()
            ->assertSee('Custom Creations');
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.custom-creations.create'))
            ->assertOk()
            ->assertSee('Add Custom Creation');
    }

    public function test_admin_can_store_creation(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $file = UploadedFile::fake()->image('design.jpg', 600, 800);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-creations.store'), [
                'title' => 'Elegant Ankara Frock',
                'image' => $file,
                'price' => 45000,
                'is_price_from' => true,
                'category' => 'ankara',
                'description' => 'Beautiful ankara design.',
                'is_active' => true,
                'sort_order' => 0,
            ]);

        $creation = CustomCreation::where('title', 'Elegant Ankara Frock')->first();
        $this->assertNotNull($creation);
        $this->assertEquals('ankara', $creation->category);
        $this->assertTrue($creation->is_price_from);
        $this->assertTrue($creation->is_active);
        $this->assertStringContainsString('custom-creations/', $creation->image_path);
    }

    public function test_admin_can_view_edit_form(): void
    {
        $admin = $this->admin();
        $creation = $this->createCreation();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.custom-creations.edit', $creation))
            ->assertOk()
            ->assertSee('Edit Custom Creation')
            ->assertSee('Birthday Princess Frock');
    }

    public function test_admin_can_update_creation(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $creation = $this->createCreation();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.custom-creations.update', $creation), [
                'title' => 'Updated Princess Frock',
                'price' => 50000,
                'category' => 'princess',
                'is_active' => true,
                'sort_order' => 5,
            ]);

        $creation->refresh();
        $this->assertEquals('Updated Princess Frock', $creation->title);
        $this->assertEquals(50000, $creation->price);
        $this->assertEquals('princess', $creation->category);
    }

    public function test_admin_can_update_with_new_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $creation = $this->createCreation();

        $file = UploadedFile::fake()->image('new-design.jpg', 600, 800);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.custom-creations.update', $creation), [
                'title' => 'Updated Title',
                'image' => $file,
                'is_active' => true,
            ]);

        $creation->refresh();
        $this->assertNotEquals('custom-creations/test-image.jpg', $creation->image_path);
        $this->assertStringContainsString('custom-creations/', $creation->image_path);
    }

    public function test_admin_can_toggle_active(): void
    {
        $admin = $this->admin();
        $creation = $this->createCreation(['is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-creations.toggle-active', $creation));

        $creation->refresh();
        $this->assertFalse($creation->is_active);
    }

    public function test_admin_can_toggle_active_back(): void
    {
        $admin = $this->admin();
        $creation = $this->createCreation(['is_active' => false]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-creations.toggle-active', $creation));

        $creation->refresh();
        $this->assertTrue($creation->is_active);
    }

    public function test_admin_can_destroy_creation(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $creation = $this->createCreation();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.custom-creations.destroy', $creation));

        $this->assertDatabaseMissing('custom_creations', ['id' => $creation->id]);
    }

    public function test_admin_destroy_cleans_up_image_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $creation = $this->createCreation(['image_path' => 'custom-creations/test-cleanup.jpg']);

        Storage::disk('public')->put('custom-creations/test-cleanup.jpg', 'fake');

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.custom-creations.destroy', $creation));

        Storage::disk('public')->assertMissing('custom-creations/test-cleanup.jpg');
    }

    // ── Auth & Authorization ─────────────────────────────────────

    public function test_unauthenticated_admin_cannot_access_crud(): void
    {
        $creation = $this->createCreation();

        $this->get(route('admin.custom-creations.index'))->assertRedirect();
        $this->get(route('admin.custom-creations.create'))->assertRedirect();
        $this->get(route('admin.custom-creations.edit', $creation))->assertRedirect();
    }

    public function test_customer_cannot_access_admin_crud(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $creation = $this->createCreation();

        $this->actingAs($customer)
            ->get(route('admin.custom-creations.index'))
            ->assertRedirect();
    }

    public function test_guest_cannot_store_creation(): void
    {
        $this->post(route('admin.custom-creations.store'), [
            'title' => 'Test',
        ])->assertRedirect();
    }
}
