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

    /**
     * Regression: locking the submit button on 'click' disabled it before HTML5
     * validation ran. This form keeps required fields on earlier steps that are hidden
     * with `d-none`, and Chrome refuses to submit - never firing 'submit' - when such a
     * field is empty or unfocusable. The button was therefore left stuck on
     * "Submitting..." with no request ever sent.
     */
    public function test_submit_button_is_not_locked_before_validation(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $response = $this->actingAs($customer)
            ->get(route('shop.custom-frock.create'));

        $response->assertOk();
        $response->assertDontSee("submitBtn.addEventListener('click'", false);
        $response->assertDontSee("'click', lockSubmitBtn", false);
    }

    public function test_submit_button_is_locked_from_the_submit_handler(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $response = $this->actingAs($customer)
            ->get(route('shop.custom-frock.create'));

        $response->assertOk();
        $response->assertSee("form.addEventListener('submit'", false);
        $response->assertSee('lockSubmitBtn()', false);
        $response->assertSee('id="submitBtn"', false);
    }
}
