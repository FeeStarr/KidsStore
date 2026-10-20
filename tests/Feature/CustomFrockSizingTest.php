<?php

namespace Tests\Feature;

use App\Models\AgeRange;
use App\Models\CustomOrder;
use App\Models\CustomOrderMeasurementField;
use App\Models\CustomOrderMeasurementGuide;
use App\Models\DeliveryCharge;
use App\Models\DeliveryLocation;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Choosing a size on the custom frock request form.
 *
 * Two paths: pick a size from the published chart, or send the child's own
 * measurements. Measurements are optional - most parents pick a size from the
 * guide - but when supplied they must actually be stored, which the request flow
 * never did before.
 */
class CustomFrockSizingTest extends TestCase
{
    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
    }

    private function seedVocabulary(): void
    {
        foreach ([['chest', 'Chest / Bust'], ['waist', 'Waist']] as $i => [$name, $label]) {
            CustomOrderMeasurementField::firstOrCreate(
                ['name' => $name],
                ['label' => $label, 'sort_order' => $i + 1, 'is_active' => true]
            );
        }

        CustomOrderMeasurementGuide::firstOrCreate(
            ['measurement_type' => 'chest', 'basis' => 'body'],
            ['name' => 'Chest / Bust', 'description' => 'Measure around the fullest part.', 'sort_order' => 1, 'is_active' => true]
        );
    }

    private function seedDelivery(): DeliveryCharge
    {
        $location = DeliveryLocation::firstOrCreate(
            ['name' => 'Lagos'],
            ['is_active' => true]
        );

        $agent = \App\Models\DeliveryAgent::create([
            'name' => 'Test Agent',
            'account_number' => 'AG' . uniqid(),
            'phone' => '08012345678',
            'is_active' => true,
        ]);

        return DeliveryCharge::create([
            'delivery_agent_id' => $agent->id,
            'delivery_location_id' => $location->id,
            'amount' => 2500,
            'is_active' => true,
        ]);
    }

    private function publishedChart(string $label = '2-3 years'): SizeChart
    {
        $chart = SizeChart::create(['name' => 'Girls Frocks', 'default_basis' => 'body']);
        $entry = SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => $label,
            'sort_order' => 1,
        ]);
        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'body',
            'min_value' => 54,
            'max_value' => 56,
        ]);

        $chart->load('entries.measurements');
        $chart->publish();

        return $chart;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'child_name' => 'Amara',
            'delivery_method' => 'delivery',
            'delivery_location_id' => DeliveryLocation::firstOrCreate(['name' => 'Lagos'], ['is_active' => true])->id,
            'delivery_address' => '12 Test Street',
            'primary_colour' => 'Pink',
            'return_policy_acknowledged' => '1',
        ], $overrides);
    }

    // ── form rendering ────────────────────────────────────────────────────

    public function test_the_form_offers_published_chart_sizes(): void
    {
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();
        $chart = $this->publishedChart();

        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.create'))
            ->assertOk()
            ->assertSee('Choose an age band')
            ->assertSee('Choose a fitting')
            ->assertSee('2-3 years')
            ->assertSee(route('shop.size-guide'));
    }

    /**
     * The measurement table is scoped to the age band being chosen, so the customer
     * is not comparing their child's size against a different age entirely.
     *
     * The rows stay in the page and are hidden by script, so without scripting the
     * table still shows every size.
     */
    public function test_the_measurement_table_is_scoped_to_the_age_band(): void
    {
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();

        $chart = SizeChart::create(['name' => 'Girls Frocks', 'default_basis' => 'body']);

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
                'min_value' => 54 + $i,
                'max_value' => 56 + $i,
            ]);
        }

        $chart->load('entries.measurements');
        $chart->publish();

        $html = $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.create'))
            ->assertOk()
            ->getContent();

        // Each row carries the age band it belongs to, so the table can be narrowed
        // to the band being chosen.
        $this->assertStringContainsString('data-frock-row-band="2-3 years"', $html);
        $this->assertStringContainsString('data-frock-row-band="4-5 years"', $html);

        // Both fittings of the first band are listed under it.
        preg_match_all('/data-frock-row-band="2-3 years"/', $html, $matches);
        $this->assertCount(2, $matches[0]);
    }

    public function test_the_form_falls_back_to_age_range_when_no_chart_is_published(): void
    {
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();
        AgeRange::firstOrCreate(['name' => '2-3 years'], ['is_active' => true]);

        // A draft chart must not be offered to customers.
        SizeChart::create(['name' => 'Draft', 'default_basis' => 'body']);

        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.create'))
            ->assertOk()
            ->assertSee('Select age...')
            ->assertDontSee('Choose a size from the guide');
    }

    public function test_the_form_shows_measurement_fields_as_optional(): void
    {
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();
        $this->publishedChart();

        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.create'))
            ->assertOk()
            ->assertSee('rather send my child')
            ->assertSee('Chest / Bust')
            ->assertSee('Measure around the fullest part.', false);
    }

    // ── storing a chosen size ─────────────────────────────────────────────

    public function test_choosing_a_chart_size_is_recorded_with_the_chart_version(): void
    {
        Storage::fake('public');
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();
        $chart = $this->publishedChart();

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.store'), $this->payload([
                'sizing_method' => CustomOrder::SIZING_STANDARD,
                'size_chart_id' => $chart->id,
                'selected_chart_size' => '2-3 years',
            ]))
            ->assertRedirect();

        $order = CustomOrder::latest('id')->first();

        $this->assertSame(CustomOrder::SIZING_STANDARD, $order->sizing_method);
        $this->assertSame($chart->id, (int) $order->size_chart_id);
        $this->assertSame(1, (int) $order->size_chart_version);
        $this->assertSame('2-3 years', $order->selected_chart_size);
        $this->assertNotNull($order->return_policy_acknowledged_at);
    }

    public function test_a_chart_size_is_required(): void
    {
        Storage::fake('public');
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();
        $chart = $this->publishedChart();

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.store'), $this->payload([
                'sizing_method' => CustomOrder::SIZING_STANDARD,
                'size_chart_id' => $chart->id,
            ]))
            ->assertSessionHasErrors('selected_chart_size');
    }

    public function test_an_unpublished_chart_cannot_be_used_to_size_a_request(): void
    {
        Storage::fake('public');
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();

        $draft = SizeChart::create(['name' => 'Draft', 'default_basis' => 'body']);

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.store'), $this->payload([
                // No chart is published, so the age range is the required field and a
                // draft chart id must not sneak in as sizing evidence.
                'standard_size' => '2-3 years',
                'sizing_method' => CustomOrder::SIZING_STANDARD,
                'size_chart_id' => $draft->id,
                'selected_chart_size' => '2-3 years',
            ]))
            ->assertRedirect();

        $order = CustomOrder::latest('id')->first();

        $this->assertNotNull($order);
        $this->assertNull(
            $order->size_chart_id,
            'A draft chart must never be recorded as the sizing evidence.'
        );
    }

    public function test_the_age_range_is_still_required_without_a_published_chart(): void
    {
        Storage::fake('public');
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.store'), $this->payload())
            ->assertSessionHasErrors('standard_size');
    }

    // ── storing supplied measurements ─────────────────────────────────────

    public function test_supplied_measurements_are_persisted(): void
    {
        Storage::fake('public');
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();
        $chart = $this->publishedChart();

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.store'), $this->payload([
                'sizing_method' => CustomOrder::SIZING_STANDARD,
                'size_chart_id' => $chart->id,
                'selected_chart_size' => '2-3 years',
                'measurements' => [
                    'chest' => ['value' => 55, 'unit' => 'cm'],
                    'waist' => ['value' => 52, 'unit' => 'cm'],
                ],
            ]))
            ->assertRedirect();

        $order = CustomOrder::latest('id')->first();

        $this->assertCount(2, $order->measurements);
        $this->assertDatabaseHas('custom_order_measurements', [
            'custom_order_id' => $order->id,
            'measurement_type' => 'chest',
            'measurement_value' => 55,
            'measurement_unit' => 'cm',
        ]);
    }

    public function test_measurements_alone_imply_the_measurements_method(): void
    {
        Storage::fake('public');
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();
        AgeRange::firstOrCreate(['name' => '2-3 years'], ['is_active' => true]);

        // No chart published, so the age range is used, but measurements were sent.
        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.store'), $this->payload([
                'standard_size' => '2-3 years',
                'measurements' => [
                    'chest' => ['value' => 55, 'unit' => 'cm'],
                ],
            ]))
            ->assertRedirect();

        $order = CustomOrder::latest('id')->first();

        $this->assertSame(CustomOrder::SIZING_MEASUREMENTS, $order->sizing_method);
    }

    public function test_blank_measurement_rows_are_not_stored(): void
    {
        Storage::fake('public');
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();
        $chart = $this->publishedChart();

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.store'), $this->payload([
                'sizing_method' => CustomOrder::SIZING_STANDARD,
                'size_chart_id' => $chart->id,
                'selected_chart_size' => '2-3 years',
                'measurements' => [
                    'chest' => ['value' => 55, 'unit' => 'cm'],
                    'waist' => ['value' => '', 'unit' => 'cm'],
                ],
            ]))
            ->assertRedirect();

        $order = CustomOrder::latest('id')->first();

        $this->assertCount(1, $order->measurements, 'A blank row is not a measurement.');
    }

    public function test_an_out_of_range_measurement_is_rejected(): void
    {
        Storage::fake('public');
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();
        $chart = $this->publishedChart();

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.store'), $this->payload([
                'sizing_method' => CustomOrder::SIZING_STANDARD,
                'size_chart_id' => $chart->id,
                'selected_chart_size' => '2-3 years',
                'measurements' => [
                    'chest' => ['value' => 999, 'unit' => 'cm'],
                ],
            ]))
            ->assertSessionHasErrors('measurements.chest.value');
    }

    public function test_a_request_without_any_sizing_choice_is_still_accepted(): void
    {
        Storage::fake('public');
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();
        $chart = $this->publishedChart();

        // Measurements are genuinely optional: a parent who simply picks a size
        // sends no measurements at all.
        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.store'), $this->payload([
                'sizing_method' => CustomOrder::SIZING_STANDARD,
                'size_chart_id' => $chart->id,
                'selected_chart_size' => '2-3 years',
            ]))
            ->assertRedirect();

        $order = CustomOrder::latest('id')->first();

        $this->assertSame(0, $order->measurements()->count());
        $this->assertSame('2-3 years', $order->selected_chart_size);
    }

    // ── snapshot + risk note ──────────────────────────────────────────────

    public function test_a_snapshot_captures_what_the_customer_was_shown(): void
    {
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();
        $chart = $this->publishedChart();
        $entry = $chart->entries->first();

        $order = CustomOrder::create([
            'custom_order_number' => 'CO-SNAP-' . uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_QUOTED,
            'child_name' => 'Amara',
            'total_amount' => 15000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
            'sizing_method' => CustomOrder::SIZING_STANDARD,
            'size_chart_id' => $chart->id,
            'size_chart_version' => $chart->version,
            'selected_chart_size' => $entry->label,
            'sizing_confirmed_at' => now(),
        ]);

        $order->captureSizingSnapshot($chart);
        $order->save();

        $snapshot = $order->fresh()->sizing_snapshot;

        $this->assertSame('Girls Frocks', $snapshot['chart']['name']);
        $this->assertSame(1, $snapshot['chart']['version']);
        $this->assertSame('2-3 years', $snapshot['selection']['age_band']);
        $this->assertNotNull($snapshot['captured_at']);
        $this->assertCount(1, $snapshot['standard_measurements']);
        $this->assertSame('chest', $snapshot['standard_measurements'][0]['type']);
        $this->assertSame('body', $snapshot['standard_measurements'][0]['basis']);
    }

    public function test_a_snapshot_survives_the_chart_being_changed(): void
    {
        $customer = $this->customer();
        $this->seedVocabulary();
        $this->seedDelivery();
        $chart = $this->publishedChart();
        $entry = $chart->entries->first();

        $order = CustomOrder::create([
            'custom_order_number' => 'CO-SNAP-' . uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_QUOTED,
            'child_name' => 'Amara',
            'total_amount' => 15000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
            'sizing_method' => CustomOrder::SIZING_STANDARD,
            'size_chart_id' => $chart->id,
            'size_chart_version' => $chart->version,
            'selected_chart_size' => $entry->label,
            'sizing_confirmed_at' => now(),
        ]);

        $order->captureSizingSnapshot($chart);
        $order->save();

        // The tailor later corrects the figure on the locked chart's entry.
        $entry->measurements()->update(['max_value' => 99]);

        $snapshot = $order->fresh()->sizing_snapshot;

        // The snapshot recorded 56; the chart now says 99. Evidence must not move.
        $this->assertSame(
            56.0,
            (float) $snapshot['standard_measurements'][0]['max'],
            'Recorded evidence must not be rewritten by later chart edits.'
        );
    }

    public function test_the_resize_risk_note_appears_only_for_measured_requests_over_time(): void
    {
        $customer = $this->customer();

        $fresh = CustomOrder::create([
            'custom_order_number' => 'CO-RISK-' . uniqid(), 'user_id' => $customer->id,
            'item_type' => 'frock', 'status' => CustomOrder::STATUS_QUOTED, 'child_name' => 'A',
            'total_amount' => 1, 'delivery_method' => 'delivery', 'delivery_address' => 'x',
            'return_policy_acknowledged' => true,
            'sizing_method' => CustomOrder::SIZING_MEASUREMENTS,
            'sizing_confirmed_at' => now(),
        ]);

        $this->assertNull($fresh->sizingRiskNote(), 'A just-confirmed request raises no concern.');

        $aged = CustomOrder::create([
            'custom_order_number' => 'CO-RISK-' . uniqid(), 'user_id' => $customer->id,
            'item_type' => 'frock', 'status' => CustomOrder::STATUS_QUOTED, 'child_name' => 'A',
            'total_amount' => 1, 'delivery_method' => 'delivery', 'delivery_address' => 'x',
            'return_policy_acknowledged' => true,
            'sizing_method' => CustomOrder::SIZING_MEASUREMENTS,
            'sizing_confirmed_at' => now()->subWeeks(3),
        ]);

        $this->assertStringContainsString('3 weeks ago', $aged->sizingRiskNote());
    }

    public function test_the_resize_risk_note_is_absent_for_size_based_requests(): void
    {
        $customer = $this->customer();

        $order = CustomOrder::create([
            'custom_order_number' => 'CO-RISK-' . uniqid(), 'user_id' => $customer->id,
            'item_type' => 'frock', 'status' => CustomOrder::STATUS_QUOTED, 'child_name' => 'A',
            'total_amount' => 1, 'delivery_method' => 'delivery', 'delivery_address' => 'x',
            'return_policy_acknowledged' => true,
            'sizing_method' => CustomOrder::SIZING_STANDARD,
            'sizing_confirmed_at' => now()->subWeeks(5),
        ]);

        $this->assertNull(
            $order->sizingRiskNote(),
            'A chart size is not a body measurement, so there is nothing to re-check.'
        );
    }
}