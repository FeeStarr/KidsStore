<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFrockWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_wires_next_button_live_state(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $response = $this->actingAs($customer)
            ->get(route('shop.custom-frock.create'));

        $response->assertOk();
        $response->assertSee('id="nextBtn"', false);
        $response->assertSee('updateNextState');
        $response->assertSee('nextBtn.disabled', false);
    }

    public function test_next_button_state_is_recalculated_on_input_and_change(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $response = $this->actingAs($customer)
            ->get(route('shop.custom-frock.create'));

        $response->assertOk();
        $response->assertSee("form.addEventListener('input', updateNextState)", false);
        $response->assertSee("form.addEventListener('change', updateNextState)", false);
    }
}
