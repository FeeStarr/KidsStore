<?php

namespace Tests\Feature;

use App\Models\AgeRange;
use App\Models\CustomCreation;
use App\Models\CustomOrder;
use App\Models\DeliveryAgent;
use App\Models\DeliveryCharge;
use App\Models\DeliveryLocation;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Starting a custom request from a design in the Custom Creations gallery.
 *
 * The link is the part customers actually use, so it is what is being tested here:
 * that every call to action carries the design through to the form, that the form
 * then verifies the design for itself, and that a request made without one behaves
 * exactly as it always did.
 */
class CustomFrockCreationPassthroughTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
    }

    private function creation(array $overrides = []): CustomCreation
    {
        return CustomCreation::create(array_merge([
            'title' => 'Beaded Party Frock',
            'image_path' => 'creations/beaded.jpg',
            'price' => 35000,
            'is_price_from' => false,
            'description' => 'A beaded party frock.',
            'age_range' => '2-3 years',
            'is_active' => true,
        ], $overrides));
    }

    private function publishedChart(): SizeChart
    {
        $chart = SizeChart::create([
            'name' => 'Girls Frocks 2026',
            'default_basis' => 'body',
            'version' => 1,
            'is_active' => true,
        ]);

        foreach ([['2-3 years', 'small'], ['2-3 years', 'medium'], ['4-5 years', 'small']] as $i => [$label, $fit]) {
            $entry = SizeChartEntry::create([
                'size_chart_id' => $chart->id,
                'label' => $label,
                'fit' => $fit,
                'sort_order' => $i + 1,
            ]);

            SizeChartMeasurement::create([
                'size_chart_entry_id' => $entry->id,
                'measurement_type' => 'chest',
                'basis' => 'body',
                'min_value' => 54,
                'max_value' => 56,
                'unit' => 'cm',
            ]);
        }

        $chart->load('entries.measurements');
        $chart->publish();

        return $chart;
    }

    private function formUrl(?int $creationId = null): string
    {
        return $creationId === null
            ? route('shop.custom-frock.create')
            : route('shop.custom-frock.create', ['creation_id' => $creationId]);
    }

    // ── the links themselves ───────────────────────────────────────────────

    public function test_the_gallery_link_carries_the_design_through_to_the_form(): void
    {
        $creation = $this->creation();

        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee($this->formUrl($creation->id), false);
    }

    public function test_the_design_page_link_carries_the_design_through_to_the_form(): void
    {
        $creation = $this->creation();

        $this->get(route('shop.custom-creations.show', $creation->id))
            ->assertOk()
            ->assertSee($this->formUrl($creation->id), false);
    }

    public function test_every_gallery_call_to_action_carries_the_design(): void
    {
        $creation = $this->creation();

        $html = $this->get(route('shop.custom-creations.index'))->assertOk()->getContent();

        // Find the anchors that lead into the request form, and check the ones
        // that are a call to action for a design. The navigation links are not one
        // of them: they name no design, and must stay that way.
        preg_match_all('/<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>/s', $html, $matches, PREG_SET_ORDER);

        $callsToAction = array_filter($matches, function ($match) {
            $text = trim(preg_replace('/<[^>]+>/', ' ', $match[2]));

            return str_contains($text, 'Start a Custom Request')
                || str_contains($text, 'Request This Creation');
        });

        $this->assertNotEmpty($callsToAction, 'the gallery must offer a way to request a design');

        foreach ($callsToAction as $match) {
            $this->assertStringContainsString(
                'creation_id='.$creation->id,
                $match[1],
                'a design call to action must carry the design through to the form'
            );
        }
    }

    public function test_the_generic_links_are_unchanged(): void
    {
        // A customer arriving from the nav or the home page names no design, and
        // must still reach a working form.
        $this->actingAs($this->customer(), 'web')
            ->get(route('shop.custom-frock.index'))
            ->assertOk()
            ->assertSee(route('shop.custom-frock.create'), false);
    }

    // ── the form honours the design ────────────────────────────────────────

    public function test_the_form_names_the_design_it_was_started_from(): void
    {
        $creation = $this->creation();

        $this->actingAs($this->customer(), 'web')
            ->get($this->formUrl($creation->id))
            ->assertOk()
            ->assertSee('Requesting: Beaded Party Frock')
            ->assertSee('Suggested age range: 2-3 years');
    }

    public function test_the_form_carries_the_design_id_when_submitted(): void
    {
        $creation = $this->creation();

        $this->actingAs($this->customer(), 'web')
            ->get($this->formUrl($creation->id))
            ->assertOk()
            ->assertSee('name="creation_id" value="'.$creation->id.'"', false);
    }

    public function test_a_design_requiring_measurement_confirmation_says_so_on_the_form(): void
    {
        $creation = $this->creation(['requires_measurement_confirmation' => true]);

        $this->actingAs($this->customer(), 'web')
            ->get($this->formUrl($creation->id))
            ->assertOk()
            ->assertSee('ask you to confirm your child');
    }

    public function test_a_standard_design_does_not_claim_to_need_measurements(): void
    {
        $creation = $this->creation();

        $this->actingAs($this->customer(), 'web')
            ->get($this->formUrl($creation->id))
            ->assertOk()
            ->assertDontSee('ask you to confirm your child');
    }

    // ── the design is verified, not trusted ─────────────────────────────────

    public function test_an_unknown_design_id_is_ignored_rather_than_breaking_the_form(): void
    {
        $this->actingAs($this->customer(), 'web')
            ->get($this->formUrl(999999))
            ->assertOk()
            ->assertDontSee('Requesting:');
    }

    public function test_an_inactive_design_is_ignored_rather_than_being_offered(): void
    {
        $creation = $this->creation(['is_active' => false]);

        $this->actingAs($this->customer(), 'web')
            ->get($this->formUrl($creation->id))
            ->assertOk()
            ->assertDontSee('Requesting: Beaded Party Frock');
    }

    public function test_a_non_numeric_design_id_is_ignored(): void
    {
        $this->actingAs($this->customer(), 'web')
            ->get(route('shop.custom-frock.create', ['creation_id' => 'not-a-number']))
            ->assertOk()
            ->assertDontSee('Requesting:');
    }

    public function test_the_form_without_a_design_behaves_as_before(): void
    {
        $this->publishedChart();

        $this->actingAs($this->customer(), 'web')
            ->get(route('shop.custom-frock.create'))
            ->assertOk()
            ->assertDontSee('Requesting:')
            ->assertDontSee('name="creation_id"', false)
            ->assertSee('Choose an age band');
    }

    // ── the age range a design suggests ────────────────────────────────────

    public function test_a_suggested_age_band_is_preselected_when_the_chart_offers_it(): void
    {
        $this->publishedChart();
        $creation = $this->creation(['age_range' => '4-5 years']);

        $html = $this->actingAs($this->customer(), 'web')
            ->get($this->formUrl($creation->id))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="4-5 years"\s+selected/', $html);
    }

    public function test_a_suggested_age_band_the_chart_does_not_offer_is_not_forced(): void
    {
        $this->publishedChart();
        $creation = $this->creation(['age_range' => '7-8 years']);

        $html = $this->actingAs($this->customer(), 'web')
            ->get($this->formUrl($creation->id))
            ->assertOk()
            ->getContent();

        // Nothing is preselected: the customer chooses.
        $this->assertDoesNotMatchRegularExpression('/<option value="7-8 years"\s+selected/', $html);
    }

    public function test_a_suggested_age_band_is_preselected_in_the_age_range_fallback(): void
    {
        AgeRange::create(['name' => '2-3 years', 'is_active' => true]);
        $creation = $this->creation(['age_range' => '2-3 years']);

        // No published chart, so the form falls back to the age range.
        $html = $this->actingAs($this->customer(), 'web')
            ->get($this->formUrl($creation->id))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="2-3 years"\s+selected/', $html);
    }

    // ── the requirement reaches the order ──────────────────────────────────

    public function test_the_design_and_its_requirement_are_recorded_on_the_order(): void
    {
        $creation = $this->creation(['requires_measurement_confirmation' => true]);
        $this->publishedChart();

        $order = $this->submit([
            'creation_id' => $creation->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->assertSame($creation->id, $order->custom_creation_id);
        $this->assertTrue($order->requires_measurement_confirmation);
    }

    public function test_a_standard_design_records_no_measurement_requirement(): void
    {
        $creation = $this->creation();
        $this->publishedChart();

        $order = $this->submit([
            'creation_id' => $creation->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'small',
        ]);

        $this->assertSame($creation->id, $order->custom_creation_id);
        $this->assertFalse($order->requires_measurement_confirmation);
    }

    public function test_a_submitted_requirement_cannot_grant_itself_an_exemption(): void
    {
        // The browser says the design needs nothing; the database says otherwise.
        $creation = $this->creation(['requires_measurement_confirmation' => true]);
        $this->publishedChart();

        $order = $this->submit([
            'creation_id' => $creation->id,
            'requires_measurement_confirmation' => '0',
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->assertTrue($order->requires_measurement_confirmation);
    }

    public function test_a_submitted_requirement_cannot_impose_one_that_does_not_exist(): void
    {
        $creation = $this->creation();
        $this->publishedChart();

        $order = $this->submit([
            'creation_id' => $creation->id,
            'requires_measurement_confirmation' => '1',
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->assertFalse($order->requires_measurement_confirmation);
    }

    public function test_a_request_without_a_design_records_no_design(): void
    {
        $this->publishedChart();

        $order = $this->submit([
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'small',
        ]);

        $this->assertNull($order->custom_creation_id);
        $this->assertFalse($order->requires_measurement_confirmation);
    }

    public function test_an_inactive_design_cannot_be_attached_to_an_order(): void
    {
        $creation = $this->creation(['is_active' => false]);
        $this->publishedChart();

        $order = $this->submit([
            'creation_id' => $creation->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->assertNull($order->custom_creation_id);
        $this->assertFalse($order->requires_measurement_confirmation);
    }

    // ── the fitting chosen ─────────────────────────────────────────────────

    public function test_the_chosen_fitting_is_recorded_with_the_size(): void
    {
        $this->publishedChart();

        $order = $this->submit([
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->assertSame('2-3 years', $order->selected_chart_size);
        $this->assertSame('medium', $order->selected_chart_fit);
    }

    public function test_the_standard_cut_is_recorded_as_no_tier(): void
    {
        $chart = SizeChart::create([
            'name' => 'Standard Only', 'default_basis' => 'body', 'version' => 1, 'is_active' => true,
        ]);
        $entry = SizeChartEntry::create([
            'size_chart_id' => $chart->id, 'label' => '2-3 years', 'sort_order' => 1,
        ]);
        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id, 'measurement_type' => 'chest', 'basis' => 'body',
            'min_value' => 54, 'max_value' => 56, 'unit' => 'cm',
        ]);
        $chart->load('entries.measurements');
        $chart->publish();

        $order = $this->submit([
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => SizeChartEntry::FIT_UNASSIGNED,
        ]);

        $this->assertSame('2-3 years', $order->selected_chart_size);
        $this->assertNull($order->selected_chart_fit);
    }

    public function test_a_fitting_the_chart_does_not_offer_at_that_age_is_refused(): void
    {
        $this->publishedChart();

        // Large fitting exists nowhere in the chart.
        $this->actingAs($this->customer(), 'web')
            ->post(route('shop.custom-frock.store'), $this->payload([
                'selected_chart_size' => '2-3 years',
                'selected_chart_fit' => 'large',
            ]))
            ->assertSessionHasErrors('selected_chart_size');
    }

    public function test_a_fitting_belonging_to_another_age_band_is_refused(): void
    {
        $this->publishedChart();

        // 4-5 years offers only the small fitting.
        $this->actingAs($this->customer(), 'web')
            ->post(route('shop.custom-frock.store'), $this->payload([
                'selected_chart_size' => '4-5 years',
                'selected_chart_fit' => 'medium',
            ]))
            ->assertSessionHasErrors('selected_chart_size');
    }

    public function test_an_unknown_fitting_value_is_refused(): void
    {
        $this->publishedChart();

        $this->actingAs($this->customer(), 'web')
            ->post(route('shop.custom-frock.store'), $this->payload([
                'selected_chart_size' => '2-3 years',
                'selected_chart_fit' => 'enormous',
            ]))
            ->assertSessionHasErrors('selected_chart_fit');
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function seedDelivery(): void
    {
        $location = DeliveryLocation::firstOrCreate(
            ['name' => 'Lagos'],
            ['is_active' => true]
        );

        $agent = DeliveryAgent::create([
            'name' => 'Test Agent',
            'account_number' => 'AG'.uniqid(),
            'phone' => '08012345678',
            'is_active' => true,
        ]);

        DeliveryCharge::create([
            'delivery_agent_id' => $agent->id,
            'delivery_location_id' => $location->id,
            'amount' => 2500,
            'is_active' => true,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        $this->seedDelivery();

        // The real form submits the chart it was rendered from, so the submission
        // does the same.
        $chart = SizeChart::published()->first();

        return array_merge([
            'child_name' => 'Amara',
            'sizing_method' => $chart ? CustomOrder::SIZING_STANDARD : null,
            'size_chart_id' => $chart?->id,
            'delivery_method' => 'delivery',
            'delivery_location_id' => DeliveryLocation::first()->id,
            'delivery_address' => '12 Test Street',
            'primary_colour' => 'Pink',
            'return_policy_acknowledged' => '1',
        ], $overrides);
    }

    private function submit(array $overrides = []): CustomOrder
    {
        $this->actingAs($this->customer(), 'web')
            ->post(route('shop.custom-frock.store'), $this->payload($overrides));

        return CustomOrder::latest('id')->first();
    }
}
