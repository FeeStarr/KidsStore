<?php

namespace Tests\Feature;

use App\Models\CustomCreation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomCreationAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function creation(array $overrides = []): CustomCreation
    {
        return CustomCreation::factory()->create(array_merge([
            'title'      => 'Rainbow Tulle Dress',
            'image_path' => 'custom-creations/rainbow.jpg',
            'is_active'  => true,
        ], $overrides));
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name'           => 'Rainbow Tulle Dress Ready',
            'slug'           => 'rainbow-tulle-'.uniqid(),
            'sku'            => 'CTA-'.uniqid(),
            'selling_price'  => 20000,
            'status'         => 'active',
            'is_active'      => true,
            'stock_quantity' => 5,
        ], $overrides));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    // ── Gallery states ───────────────────────────────────────────

    public function test_gallery_unlinked_shows_age_range_and_request_only_state(): void
    {
        $this->creation(['age_range' => '3-5 years']);

        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('Ages 3-5 years')
            ->assertDontSee('Can Be Created on Request')
            ->assertSee('Request This Creation')
            ->assertDontSee('Available to Order')
            ->assertDontSee('Currently Out of Stock')
            ->assertDontSee('View & Order');
    }

    public function test_gallery_linked_in_stock_shows_available_with_both_buttons(): void
    {
        $product = $this->product();
        $this->creation(['age_range' => '6-8 years', 'product_id' => $product->id]);

        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('Ages 6-8 years')
            ->assertSee('Available to Order')
            ->assertSee('View & Order')
            ->assertSee('Start Custom Order')
            ->assertSee(route('shop.products.show', $product->id), false);
    }

    public function test_gallery_linked_out_of_stock_keeps_request_and_view_product_options(): void
    {
        $product = $this->product(['stock_quantity' => 0]);
        $this->creation(['product_id' => $product->id]);

        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('Currently Out of Stock')
            ->assertSee('Request This Creation')
            ->assertSee('View Product')
            ->assertSee(route('shop.products.show', $product->id), false)
            ->assertDontSee('View & Order');
    }

    public function test_gallery_inactive_linked_product_falls_back_to_request_only(): void
    {
        $product = $this->product(['status' => 'inactive']);
        $this->creation(['product_id' => $product->id]);

        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertDontSee('Can Be Created on Request')
            ->assertSee('Request This Creation')
            ->assertDontSee('View & Order')
            ->assertDontSee(route('shop.products.show', $product->id), false);
    }

    // ── Detail page states ───────────────────────────────────────

    public function test_detail_available_shows_age_range_badge_and_both_buttons(): void
    {
        $product = $this->product();
        $this->creation(['age_range' => '2-4 years', 'product_id' => $product->id]);

        $this->get(route('shop.custom-creations.show', CustomCreation::first()->id))
            ->assertOk()
            ->assertSee('Ages 2-4 years')
            ->assertSee('Available to Order')
            ->assertSee('View & Order')
            ->assertSee('Start Custom Order');
    }

    public function test_detail_out_of_stock_never_hides_the_custom_order_option(): void
    {
        $product = $this->product(['stock_quantity' => 0]);
        $this->creation(['product_id' => $product->id]);

        $this->get(route('shop.custom-creations.show', CustomCreation::first()->id))
            ->assertOk()
            ->assertSee('Currently Out of Stock')
            ->assertSee('Request This Creation')
            ->assertSee('View Product')
            ->assertDontSee('View & Order');
    }

    public function test_detail_request_only_hides_badge_but_keeps_cta(): void
    {
        $this->creation();

        $this->get(route('shop.custom-creations.show', CustomCreation::first()->id))
            ->assertOk()
            ->assertDontSee('Can Be Created on Request')
            ->assertSee('Request This Creation');
    }

    // ── FK deletion fallback ─────────────────────────────────────

    public function test_deleting_linked_product_nulls_fk_and_falls_back_to_request_only(): void
    {
        $product = $this->product();
        $this->creation(['product_id' => $product->id]);

        $product->delete();

        $this->assertDatabaseHas('custom_creations', [
            'id'         => CustomCreation::first()->id,
            'product_id' => null,
        ]);

        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertDontSee('Can Be Created on Request')
            ->assertDontSee('View & Order');
    }

    // ── Admin CRUD ───────────────────────────────────────────────

    public function test_admin_store_persists_age_range_and_product_link(): void
    {
        Storage::fake('public');
        $product = $this->product();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.custom-creations.store'), [
                'title'      => 'Ankara Party Frock',
                'image'      => UploadedFile::fake()->image('ankara.jpg', 600, 800),
                'age_range'  => '4-6 years',
                'product_id' => $product->id,
                'is_active'  => true,
            ])
            ->assertRedirect(route('admin.custom-creations.index'));

        $this->assertDatabaseHas('custom_creations', [
            'title'      => 'Ankara Party Frock',
            'age_range'  => '4-6 years',
            'product_id' => $product->id,
        ]);
    }

    public function test_admin_update_can_unlink_product(): void
    {
        $product = $this->product();
        $creation = $this->creation(['product_id' => $product->id, 'age_range' => '3-5 years']);

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.custom-creations.update', $creation), [
                'title'      => $creation->title,
                'product_id' => '',
                'age_range'  => '7-9 years',
                'is_active'  => true,
            ])
            ->assertRedirect(route('admin.custom-creations.index'));

        $this->assertDatabaseHas('custom_creations', [
            'id'         => $creation->id,
            'product_id' => null,
            'age_range'  => '7-9 years',
        ]);
    }

    public function test_admin_store_rejects_unknown_product(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin(), 'admin')
            ->from(route('admin.custom-creations.create'))
            ->post(route('admin.custom-creations.store'), [
                'title'      => 'Bad Link Frock',
                'image'      => UploadedFile::fake()->image('bad.jpg', 600, 800),
                'product_id' => 999999,
                'is_active'  => true,
            ])
            ->assertRedirect(route('admin.custom-creations.create'))
            ->assertSessionHasErrors('product_id');
    }

    public function test_admin_create_form_lists_products_for_linking(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.custom-creations.create'))
            ->assertOk()
            ->assertSee('Age Range')
            ->assertSee('Linked Product')
            ->assertSee($product->name);
    }

    // ── API ──────────────────────────────────────────────────────

    public function test_api_exposes_age_range_and_computed_availability(): void
    {
        $linked = $this->product();
        $this->creation(['age_range' => '3-5 years', 'product_id' => $linked->id]);

        $this->getJson('/api/v1/custom-creations')
            ->assertOk()
            ->assertJsonFragment([
                'age_range'    => '3-5 years',
                'product_id'   => $linked->id,
                'availability' => 'available',
                'is_available' => true,
            ]);
    }
}
