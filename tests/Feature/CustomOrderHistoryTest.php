<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderCustomization;
use App\Models\CustomOrderFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER]);
    }

    // ── Index / Listing ─────────────────────────────────────────

    public function test_customer_with_no_orders_sees_empty_state(): void
    {
        $this->actingAs($this->customer())
            ->get(route('shop.custom-frock.index'))
            ->assertOk()
            ->assertSee('No custom orders yet')
            ->assertSee('Create a Custom Order');
    }

    public function test_customer_sees_their_custom_orders(): void
    {
        $user = $this->customer();
        $order = CustomOrder::factory()->submitted()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('shop.custom-frock.index'))
            ->assertOk()
            ->assertSee($order->custom_order_number);
    }

    public function test_customer_does_not_see_other_customers_orders(): void
    {
        $user = $this->customer();
        $other = CustomOrder::factory()->submitted()->create();

        $this->actingAs($user)
            ->get(route('shop.custom-frock.index'))
            ->assertOk()
            ->assertDontSee($other->custom_order_number);
    }

    public function test_index_uses_pagination(): void
    {
        $user = $this->customer();
        CustomOrder::factory()->submitted()->count(15)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('shop.custom-frock.index'));
        $response->assertOk();

        $view = $response->getOriginalContent();
        $orders = $view->getData()['orders'];
        $this->assertGreaterThan(1, $orders->lastPage());
    }

    public function test_index_displays_thumbnail_when_file_exists(): void
    {
        Storage::fake('custom_orders');
        $user = $this->customer();
        $order = CustomOrder::factory()->submitted()->create(['user_id' => $user->id]);

        CustomOrderFile::create([
            'custom_order_id' => $order->id,
            'file_type' => 'reference_image',
            'file_path' => 'test/image.jpg',
            'original_filename' => 'design.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('shop.custom-frock.index'));
        $response->assertOk();
        $response->assertSee('object-fit:cover');
    }

    public function test_index_shows_fallback_when_no_file(): void
    {
        $user = $this->customer();
        CustomOrder::factory()->submitted()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('shop.custom-frock.index'))
            ->assertSee('bi-scissors');
    }

    public function test_index_shows_payment_badge_when_paid(): void
    {
        $user = $this->customer();
        CustomOrder::factory()->completed()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('shop.custom-frock.index'))
            ->assertSee('Paid');
    }

    // ── Filtering ───────────────────────────────────────────────

    public function test_filter_active_excludes_completed_and_cancelled(): void
    {
        $user = $this->customer();
        $active = CustomOrder::factory()->submitted()->create(['user_id' => $user->id]);
        $done = CustomOrder::factory()->completed()->create(['user_id' => $user->id]);
        $killed = CustomOrder::factory()->cancelled()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('shop.custom-frock.index', ['filter' => 'active']));
        $response->assertOk();

        $view = $response->getOriginalContent();
        $orders = $view->getData()['orders'];
        $this->assertTrue($orders->contains($active));
        $this->assertFalse($orders->contains($done));
        $this->assertFalse($orders->contains($killed));
    }

    public function test_filter_completed_only_shows_completed(): void
    {
        $user = $this->customer();
        $active = CustomOrder::factory()->submitted()->create(['user_id' => $user->id]);
        $done = CustomOrder::factory()->completed()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('shop.custom-frock.index', ['filter' => 'completed']));
        $response->assertOk();

        $view = $response->getOriginalContent();
        $orders = $view->getData()['orders'];
        $this->assertFalse($orders->contains($active));
        $this->assertTrue($orders->contains($done));
    }

    public function test_filter_cancelled_shows_cancelled_and_rejected(): void
    {
        $user = $this->customer();
        $cancelled = CustomOrder::factory()->cancelled()->create(['user_id' => $user->id]);
        $rejected = CustomOrder::factory()->rejected()->create(['user_id' => $user->id]);
        $active = CustomOrder::factory()->submitted()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('shop.custom-frock.index', ['filter' => 'cancelled']));
        $response->assertOk();

        $view = $response->getOriginalContent();
        $orders = $view->getData()['orders'];
        $this->assertTrue($orders->contains($cancelled));
        $this->assertTrue($orders->contains($rejected));
        $this->assertFalse($orders->contains($active));
    }

    // ── Show / Details ──────────────────────────────────────────

    public function test_customer_can_view_own_order_details(): void
    {
        $user = $this->customer();
        $order = CustomOrder::factory()->submitted()->create(['user_id' => $user->id]);
        CustomOrderCustomization::create([
            'custom_order_id' => $order->id,
            'attribute' => 'primary_colour',
            'value' => 'Pink',
        ]);

        $this->actingAs($user)
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('Customization Details')
            ->assertSee('Pink');
    }

    public function test_customer_cannot_view_other_customers_order(): void
    {
        $user = $this->customer();
        $other = CustomOrder::factory()->submitted()->create();

        $this->actingAs($user)
            ->get(route('shop.custom-frock.show', $other))
            ->assertStatus(403);
    }

    public function test_show_displays_payment_info_when_priced(): void
    {
        $user = $this->customer();
        $order = CustomOrder::factory()->submitted()->withPrice(35000, 20000)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('35,000')
            ->assertSee('20,000')
            ->assertSee('Outstanding')
            ->assertSee('15,000');
    }

    public function test_show_displays_customer_notes(): void
    {
        $user = $this->customer();
        $order = CustomOrder::factory()->submitted()->create([
            'user_id' => $user->id,
            'customer_notes' => 'Please use soft fabric',
        ]);

        $this->actingAs($user)
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('Please use soft fabric')
            ->assertSee('Your Notes');
    }

    public function test_show_displays_customer_visible_notes(): void
    {
        $user = $this->customer();
        $order = CustomOrder::factory()->submitted()->create([
            'user_id' => $user->id,
            'customer_visible_notes' => 'We have started working on your order.',
        ]);

        $this->actingAs($user)
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('We have started working on your order.')
            ->assertSee('Note from KidsFlairr');
    }

    public function test_show_hides_empty_fields(): void
    {
        $user = $this->customer();
        $order = CustomOrder::factory()->submitted()->create([
            'user_id' => $user->id,
            'customer_notes' => null,
            'customer_visible_notes' => null,
        ]);

        $this->actingAs($user)
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertDontSee('Your Notes')
            ->assertDontSee('Note from KidsFlairr');
    }

    // ── Create Similar Order ────────────────────────────────────

    public function test_create_similar_redirects_to_form_with_prepopulated_data(): void
    {
        $user = $this->customer();
        $order = CustomOrder::factory()->completed()->create([
            'user_id' => $user->id,
            'child_name' => 'Amara',
            'child_age' => 5,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'custom_colour_description' => 'Bright pink with gold accents',
        ]);
        CustomOrderCustomization::create([
            'custom_order_id' => $order->id,
            'attribute' => 'primary_colour',
            'value' => 'Pink',
        ]);
        CustomOrderCustomization::create([
            'custom_order_id' => $order->id,
            'attribute' => 'standard_size',
            'value' => '4-5 Years',
        ]);

        $response = $this->actingAs($user)
            ->get(route('shop.custom-frock.create-similar', $order));

        $response->assertRedirect(route('shop.custom-frock.create'));
        $response->assertSessionHas('custom_order_draft', function ($draft) {
            return $draft['child_name'] === 'Amara'
                && $draft['child_age'] === 5
                && $draft['primary_colour'] === 'Pink'
                && $draft['standard_size'] === '4-5 Years'
                && $draft['delivery_method'] === 'delivery';
        });
    }

    public function test_create_similar_does_not_copy_payment_status(): void
    {
        $user = $this->customer();
        $order = CustomOrder::factory()->completed()->create([
            'user_id' => $user->id,
            'payment_status' => 'paid',
            'amount_paid' => 35000,
            'total_amount' => 35000,
        ]);

        $this->actingAs($user)->get(route('shop.custom-frock.create-similar', $order));

        $draft = Session::get('custom_order_draft');
        $this->assertArrayNotHasKey('payment_status', $draft);
        $this->assertArrayNotHasKey('amount_paid', $draft);
        $this->assertArrayNotHasKey('total_amount', $draft);
    }

    public function test_create_similar_does_not_copy_status_or_timestamps(): void
    {
        $user = $this->customer();
        $order = CustomOrder::factory()->completed()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('shop.custom-frock.create-similar', $order));

        $draft = Session::get('custom_order_draft');
        $this->assertArrayNotHasKey('status', $draft);
        $this->assertArrayNotHasKey('submitted_at', $draft);
        $this->assertArrayNotHasKey('completed_at', $draft);
    }

    public function test_create_similar_cannot_be_used_by_other_customer(): void
    {
        $order = CustomOrder::factory()->completed()->create();

        $this->actingAs($this->customer())
            ->get(route('shop.custom-frock.create-similar', $order))
            ->assertStatus(403);
    }

    public function test_create_form_restores_prepopulated_data(): void
    {
        $user = $this->customer();
        Session::put('custom_order_draft', [
            'child_name' => 'Amara',
            'child_age' => 5,
            'primary_colour' => 'Pink',
            'standard_size' => '4-5 Years',
        ]);

        $this->actingAs($user)
            ->get(route('shop.custom-frock.create'))
            ->assertOk()
            ->assertSee('Amara')
            ->assertSee('Pink');
    }

    // ── Navigation ──────────────────────────────────────────────

    public function test_nav_links_to_custom_order_listing(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->get(route('shop.home'))
            ->assertSee(route('shop.custom-frock.index'))
            ->assertSee('My Custom Orders');
    }

    // ── Integration: existing submission still works ─────────────

    public function test_custom_order_submission_still_works(): void
    {
        $user = $this->customer();

        $response = $this->actingAs($user)->post(route('shop.custom-frock.store'), [
            'child_name' => 'Test Child',
            'delivery_method' => 'delivery',
            'delivery_address' => '123 Test Street',
            'primary_colour' => 'Blue',
            'standard_size' => '2-3 Years',
            'return_policy_acknowledged' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('custom_orders', [
            'user_id' => $user->id,
            'child_name' => 'Test Child',
            'status' => CustomOrder::STATUS_SUBMITTED,
        ]);
    }
}
