<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
    }

    public function test_index_page_renders(): void
    {
        Coupon::create([
            'code'           => 'SAVE10',
            'name'           => 'Save 10',
            'discount_type'  => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'status'         => Coupon::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.coupons.index'));

        $response->assertOk()->assertSee('save10');
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.coupons.create'))
            ->assertOk();
    }

    public function test_store_creates_coupon_and_redirects(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.coupons.store'), [
            'code'          => 'KIDS500',
            'name'          => 'Kids 500',
            'discount_type' => Coupon::TYPE_FIXED_AMOUNT,
            'discount_value' => 500,
            'applies_to'    => Coupon::APPLIES_REGULAR_PRICE_ONLY,
            'status'        => Coupon::STATUS_ACTIVE,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('coupons', ['code' => 'kids500']);
    }

    public function test_update_edits_coupon(): void
    {
        $coupon = Coupon::create([
            'code'           => 'OLD10',
            'name'           => 'Old',
            'discount_type'  => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'status'         => Coupon::STATUS_INACTIVE,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.coupons.update', $coupon), [
            'code'          => 'NEW20',
            'name'          => 'New Name',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'applies_to'    => Coupon::APPLIES_ALL,
            'status'        => Coupon::STATUS_ACTIVE,
        ]);

        $response->assertRedirect();
        $coupon->refresh();
        $this->assertEquals('new20', $coupon->code);
        $this->assertEquals('New Name', $coupon->name);
        $this->assertEquals(20, (float) $coupon->discount_value);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        Coupon::create([
            'code'           => 'DUP10',
            'name'           => 'First',
            'discount_type'  => Coupon::TYPE_FIXED_AMOUNT,
            'discount_value' => 100,
            'status'         => Coupon::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.coupons.store'), [
            'code'          => 'dup10',
            'name'          => 'Second',
            'discount_type' => Coupon::TYPE_FIXED_AMOUNT,
            'discount_value' => 200,
            'applies_to'    => Coupon::APPLIES_ALL,
            'status'        => Coupon::STATUS_ACTIVE,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_activation_toggles(): void
    {
        $coupon = Coupon::create([
            'code'           => 'TOGGLE1',
            'name'           => 'Toggle',
            'discount_type'  => Coupon::TYPE_FIXED_AMOUNT,
            'discount_value' => 50,
            'status'         => Coupon::STATUS_INACTIVE,
        ]);

        $this->actingAs($this->admin())->post(route('admin.coupons.activate', $coupon));
        $this->assertEquals(Coupon::STATUS_ACTIVE, $coupon->fresh()->status);

        $this->actingAs($this->admin())->post(route('admin.coupons.deactivate', $coupon));
        $this->assertEquals(Coupon::STATUS_INACTIVE, $coupon->fresh()->status);
    }
}