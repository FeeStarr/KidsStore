<?php

namespace Tests\Feature;

use App\Exceptions\ProductionGateException;
use App\Models\CustomCreation;
use App\Models\CustomOrder;
use App\Models\CustomOrderMeasurementField;
use App\Models\CustomOrderMeasurementGuide;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use App\Models\User;
use App\Services\CustomOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The sizing evidence, and the gate that refuses to cut without it.
 *
 * This is the commit that decides whether a sizing dispute a year from now can be
 * settled. Three things have to stay apart for the life of an order: what the
 * customer chose, what they agreed to, and what the frock is actually cut to. The
 * tests below exist to make sure a comparison figure never becomes the cutting
 * basis, that a standard measurement never replaces the customer's own, and that
 * evidence already agreed to cannot be quietly rewritten.
 */
class SizingConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function vocabulary(): void
    {
        foreach ([['chest', 'Chest / Bust'], ['waist', 'Waist']] as $i => [$name, $label]) {
            CustomOrderMeasurementField::firstOrCreate(
                ['name' => $name],
                ['label' => $label, 'sort_order' => $i + 1, 'is_active' => true]
            );
        }

        CustomOrderMeasurementGuide::firstOrCreate(
            ['measurement_type' => 'chest', 'basis' => 'body'],
            [
                'name' => 'Chest / Bust',
                'description' => 'Measure around the fullest part of the child chest.',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );
    }

    private function chart(?string $fit = 'medium'): SizeChart
    {
        $chart = SizeChart::create([
            'name' => 'Girls Frocks 2026',
            'default_basis' => 'body',
            'version' => 1,
            'is_active' => true,
        ]);

        $entry = SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => '2-3 years',
            'fit' => $fit,
            'sort_order' => 1,
        ]);

        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'body',
            'min_value' => 54,
            'max_value' => 56,
            'unit' => 'cm',
        ]);

        $chart->load('entries.measurements');
        $chart->publish();

        return $chart;
    }

    private function order(array $overrides = []): CustomOrder
    {
        $customer = $this->customer();

        return CustomOrder::create(array_merge([
            'custom_order_number' => 'CO-SIZE-'.uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_SUBMITTED,
            'child_name' => 'Amara',
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
            'sizing_method' => CustomOrder::SIZING_STANDARD,
        ], $overrides));
    }

    private function confirmationPayload(array $overrides = []): array
    {
        return array_merge([
            'sizing_confirmed' => '1',
            'sizing_policy_acknowledged' => '1',
        ], $overrides);
    }

    private function confirm(CustomOrder $order, array $overrides = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? User::find($order->user_id), 'web')
            ->post(route('shop.custom-frock.confirm-sizing', $order), $this->confirmationPayload($overrides));
    }

    // ── the three paths ───────────────────────────────────────────────────

    public function test_a_standard_design_needs_no_child_measurements(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'size_chart_version' => $chart->version,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
            'requires_measurement_confirmation' => false,
        ]);

        $this->confirm($order)->assertSessionHasNoErrors();

        $order->refresh();

        $this->assertNotNull($order->sizing_confirmed_at);
        $this->assertNotNull($order->sizing_policy_acknowledged_at);
        $this->assertSame(0, $order->comparisonMeasurements()->count());
    }

    public function test_a_design_requiring_confirmation_needs_comparison_measurements(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
            'requires_measurement_confirmation' => true,
        ]);

        $this->confirm($order)->assertSessionHasErrors('comparison_measurements');

        $this->assertNull($order->fresh()->sizing_confirmed_at);
    }

    public function test_a_flagged_design_with_submitted_comparison_measurements_can_confirm(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
            'requires_measurement_confirmation' => true,
        ]);

        $this->confirm($order, [
            'comparison_measurements' => ['chest' => ['value' => 55.5, 'unit' => 'cm']],
        ])->assertSessionHasNoErrors();

        $order->refresh();

        $this->assertNotNull($order->sizing_confirmed_at);
        $this->assertSame(1, $order->comparisonMeasurements()->count());
        $this->assertSame('submitted', $order->sizing_snapshot['comparison_measurements_source']);
    }

    public function test_existing_measurements_serve_as_comparison_only_when_acknowledged(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
            'sizing_method' => CustomOrder::SIZING_MEASUREMENTS,
            'requires_measurement_confirmation' => true,
        ]);
        $order->measurements()->create([
            'measurement_type' => 'chest',
            'measurement_value' => 55,
            'measurement_unit' => 'cm',
        ]);

        $this->confirm($order, ['reuse_production_measurements' => '1'])->assertSessionHasNoErrors();

        $order->refresh();

        // The production figures are untouched, and a separate comparison record exists.
        $this->assertSame(1, $order->measurements()->count());
        $this->assertSame(1, $order->comparisonMeasurements()->count());
        $this->assertSame(55.0, (float) $order->measurements()->first()->measurement_value);
        $this->assertSame(55.0, (float) $order->comparisonMeasurements()->first()->measurement_value);
        $this->assertSame('reused_from_production', $order->sizing_snapshot['comparison_measurements_source']);
    }

    public function test_existing_measurements_are_not_copied_without_the_acknowledgement(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'sizing_method' => CustomOrder::SIZING_MEASUREMENTS,
            'requires_measurement_confirmation' => true,
        ]);
        $order->measurements()->create([
            'measurement_type' => 'chest',
            'measurement_value' => 55,
            'measurement_unit' => 'cm',
        ]);

        // No acknowledgement, so the customer must supply fresh figures.
        $this->confirm($order)->assertSessionHasErrors('comparison_measurements');

        $this->assertSame(0, $order->fresh()->comparisonMeasurements()->count());
    }

    public function test_customer_measurements_remain_the_production_basis(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'sizing_method' => CustomOrder::SIZING_MEASUREMENTS,
            'requires_measurement_confirmation' => true,
        ]);
        $order->measurements()->create([
            'measurement_type' => 'chest',
            'measurement_value' => 55,
            'measurement_unit' => 'cm',
        ]);

        $this->confirm($order, ['reuse_production_measurements' => '1'])->assertSessionHasNoErrors();

        $snapshot = $order->fresh()->sizing_snapshot;

        $this->assertSame(CustomOrder::SIZING_MEASUREMENTS, $snapshot['production_basis']);
        // The comparison figures are recorded as comparison, never as the basis.
        $this->assertCount(1, $snapshot['production_measurements']);
        $this->assertCount(1, $snapshot['comparison_measurements']);
        // JSON turns a whole float into an integer, so the values are compared loosely.
        $this->assertEquals([55.0], array_column($snapshot['production_measurements'], 'value'));
    }

    // ── confirmation validation ────────────────────────────────────────────

    public function test_a_missing_confirmation_is_refused(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order(['size_chart_id' => $chart->id, 'selected_chart_size' => '2-3 years']);

        $this->confirm($order, ['sizing_confirmed' => null])->assertSessionHasErrors('sizing_confirmed');

        $this->assertNull($order->fresh()->sizing_confirmed_at);
    }

    public function test_a_tampered_confirmation_value_is_refused(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order(['size_chart_id' => $chart->id, 'selected_chart_size' => '2-3 years']);

        // "required" alone would have accepted any non-empty value.
        $this->confirm($order, ['sizing_confirmed' => 'no'])
            ->assertSessionHasErrors('sizing_confirmed');

        $this->assertNull($order->fresh()->sizing_confirmed_at);
    }

    public function test_a_tampered_policy_acknowledgement_is_refused(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order(['size_chart_id' => $chart->id, 'selected_chart_size' => '2-3 years']);

        $this->confirm($order, ['sizing_policy_acknowledged' => '0'])
            ->assertSessionHasErrors('sizing_policy_acknowledged');

        $this->assertNull($order->fresh()->sizing_policy_acknowledged_at);
    }

    public function test_someone_else_cannot_confirm_an_order(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order(['size_chart_id' => $chart->id, 'selected_chart_size' => '2-3 years']);

        $this->confirm($order, [], $this->customer())->assertForbidden();

        $this->assertNull($order->fresh()->sizing_confirmed_at);
    }

    public function test_sizing_cannot_be_confirmed_twice(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order(['size_chart_id' => $chart->id, 'selected_chart_size' => '2-3 years']);

        $this->confirm($order)->assertSessionHasNoErrors();
        $first = $order->fresh()->sizing_snapshot;

        // A second confirmation is refused, and the evidence is left as it was.
        $this->confirm($order)->assertSessionHas('error');

        $this->assertEquals($first, $order->fresh()->sizing_snapshot);
    }

    public function test_sizing_cannot_be_confirmed_after_production_started(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'production_started_at' => now()->subMinute(),
        ]);

        $this->confirm($order)->assertSessionHas('error');

        $this->assertNull($order->fresh()->sizing_confirmed_at);
    }

    // ── the snapshot ──────────────────────────────────────────────────────

    public function test_the_snapshot_records_the_fitting_as_null_when_no_tier_was_assigned(): void
    {
        $this->vocabulary();
        $chart = $this->chart(null);
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => null,
        ]);

        $this->confirm($order)->assertSessionHasNoErrors();

        $snapshot = $order->fresh()->sizing_snapshot;

        $this->assertNull($snapshot['selection']['fit']);
        $this->assertNull($snapshot['selection']['fit_label']);
        $this->assertNotSame('medium', $snapshot['selection']['fit']);
    }

    public function test_the_snapshot_records_the_standard_figures_shown_to_the_customer(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->confirm($order)->assertSessionHasNoErrors();

        $figures = $order->fresh()->sizing_snapshot['standard_measurements'];

        $this->assertCount(1, $figures);
        $this->assertSame('chest', $figures[0]['type']);
        $this->assertEquals(54, $figures[0]['min']);
        $this->assertEquals(56, $figures[0]['max']);
        $this->assertSame('cm', $figures[0]['unit']);
    }

    public function test_the_snapshot_records_the_complete_guide_wording(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->confirm($order)->assertSessionHasNoErrors();

        $guides = $order->fresh()->sizing_snapshot['guides'];

        $this->assertCount(1, $guides);
        $this->assertSame('chest', $guides[0]['measurement_type']);
        $this->assertSame('body', $guides[0]['basis']);
        $this->assertSame('Chest / Bust', $guides[0]['name']);
        $this->assertSame('Measure around the fullest part of the child chest.', $guides[0]['description']);
        $this->assertTrue($guides[0]['is_active']);
    }

    public function test_the_snapshot_records_the_design_and_its_requirement(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $creation = CustomCreation::create([
            'title' => 'Beaded Party Frock',
            'image_path' => 'creations/beaded.jpg',
            'age_range' => '2-3 years',
            'is_active' => true,
            'requires_measurement_confirmation' => true,
        ]);

        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'custom_creation_id' => $creation->id,
            'requires_measurement_confirmation' => true,
        ]);

        $this->confirm($order, [
            'comparison_measurements' => ['chest' => ['value' => 55, 'unit' => 'cm']],
        ])->assertSessionHasNoErrors();

        $design = $order->fresh()->sizing_snapshot['design'];

        $this->assertSame($creation->id, $design['creation_id']);
        $this->assertSame('Beaded Party Frock', $design['title']);
        $this->assertTrue($design['requires_measurement_confirmation']);
    }

    // ── immutability ──────────────────────────────────────────────────────

    public function test_editing_a_guide_after_confirmation_cannot_rewrite_the_snapshot(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->confirm($order)->assertSessionHasNoErrors();
        $before = $order->fresh()->sizing_snapshot;

        CustomOrderMeasurementGuide::where('measurement_type', 'chest')
            ->where('basis', 'body')
            ->update(['description' => 'Something completely different.']);

        $after = $order->fresh()->sizing_snapshot;

        $this->assertEquals($before, $after);
        $this->assertSame(
            'Measure around the fullest part of the child chest.',
            $after['guides'][0]['description']
        );
    }

    public function test_a_new_chart_version_after_confirmation_cannot_rewrite_the_snapshot(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'size_chart_version' => 1,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->confirm($order)->assertSessionHasNoErrors();
        $before = $order->fresh()->sizing_snapshot;

        // A new version is published with different figures.
        $newVersion = SizeChart::create([
            'name' => 'Girls Frocks 2026',
            'default_basis' => 'body',
            'version' => 2,
            'is_active' => true,
        ]);
        $entry = SizeChartEntry::create([
            'size_chart_id' => $newVersion->id,
            'label' => '2-3 years',
            'fit' => 'medium',
            'sort_order' => 1,
        ]);
        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'body',
            'min_value' => 99,
            'max_value' => 101,
            'unit' => 'cm',
        ]);
        $newVersion->load('entries.measurements');
        $newVersion->publish();

        $after = $order->fresh()->sizing_snapshot;

        $this->assertEquals($before, $after);
        $this->assertEquals(54, $after['standard_measurements'][0]['min']);
    }

    public function test_a_design_flag_change_after_confirmation_cannot_rewrite_the_snapshot(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $creation = CustomCreation::create([
            'title' => 'Beaded Party Frock',
            'image_path' => 'creations/beaded.jpg',
            'is_active' => true,
            'requires_measurement_confirmation' => false,
        ]);

        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'custom_creation_id' => $creation->id,
            'requires_measurement_confirmation' => false,
        ]);

        $this->confirm($order)->assertSessionHasNoErrors();
        $before = $order->fresh()->sizing_snapshot;

        $creation->update(['requires_measurement_confirmation' => true]);

        $after = $order->fresh()->sizing_snapshot;

        $this->assertEquals($before, $after);
        $this->assertFalse($after['design']['requires_measurement_confirmation']);
    }

    // ── the production gate ───────────────────────────────────────────────

    public function test_production_is_refused_without_a_confirmation(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'status' => CustomOrder::STATUS_PAID,
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.custom-orders.start-production', $order))
            ->assertSessionHas('error');

        $order->refresh();

        $this->assertSame(CustomOrder::STATUS_PAID, $order->status);
        $this->assertNull($order->production_started_at);
    }

    public function test_production_is_refused_for_a_flagged_design_without_comparison_measurements(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'status' => CustomOrder::STATUS_PAID,
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'requires_measurement_confirmation' => true,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.custom-orders.start-production', $order))
            ->assertSessionHas('error');

        $this->assertSame(CustomOrder::STATUS_PAID, $order->fresh()->status);
    }

    public function test_a_refused_production_leaves_the_order_untouched(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'status' => CustomOrder::STATUS_PAID,
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
        ]);

        $historyBefore = $order->statusHistory()->count();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.custom-orders.start-production', $order))
            ->assertSessionHas('error');

        $order->refresh();

        $this->assertSame(CustomOrder::STATUS_PAID, $order->status);
        $this->assertNull($order->production_started_at);
        $this->assertNull($order->quoted_at);
        $this->assertSame($historyBefore, $order->statusHistory()->count());
    }

    public function test_the_service_refuses_directly_and_throws(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'status' => CustomOrder::STATUS_PAID,
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
        ]);

        $this->expectException(ProductionGateException::class);

        app(CustomOrderService::class)->startProduction($order, null);
    }

    public function test_a_confirmed_order_can_start_production(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'status' => CustomOrder::STATUS_PAID,
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->confirm($order)->assertSessionHasNoErrors();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.custom-orders.start-production', $order))
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(CustomOrder::STATUS_IN_PRODUCTION, $order->status);
        $this->assertNotNull($order->production_started_at);
    }

    public function test_a_changed_basis_after_confirmation_blocks_production(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'status' => CustomOrder::STATUS_PAID,
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->confirm($order)->assertSessionHasNoErrors();

        // The production measurements change after the customer agreed to them.
        $order->measurements()->create([
            'measurement_type' => 'chest',
            'measurement_value' => 60,
            'measurement_unit' => 'cm',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.custom-orders.start-production', $order))
            ->assertSessionHas('error');

        $this->assertSame(CustomOrder::STATUS_PAID, $order->fresh()->status);
    }

    // ── atomicity ─────────────────────────────────────────────────────────

    public function test_a_second_confirmation_cannot_write_a_conflicting_snapshot(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $service = app(CustomOrderService::class);

        $service->confirmSizing($order, [], false, $order->user_id);
        $first = $order->fresh()->sizing_snapshot;

        try {
            $service->confirmSizing($order->fresh(), [], false, $order->user_id);
            $this->fail('a second confirmation should have been refused');
        } catch (\RuntimeException) {
            // Expected.
        }

        $this->assertEquals($first, $order->fresh()->sizing_snapshot);
    }

    // ── the confirmation screen ───────────────────────────────────────────

    public function test_the_confirmation_screen_shows_the_basis_and_the_figures(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->actingAs(User::find($order->user_id), 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('Please confirm the sizing')
            ->assertSee('2-3 years')
            ->assertSee('Medium fitting')
            ->assertSee('54')
            ->assertSee('56')
            ->assertSee('Measure around the fullest part of the child chest.');
    }

    public function test_the_confirmation_screen_disappears_once_confirmed(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->confirm($order)->assertSessionHasNoErrors();

        $this->actingAs(User::find($order->user_id), 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('Sizing confirmed')
            ->assertDontSee('Please confirm the sizing');
    }

    // ── a standard fitting is a starting point, not a guarantee ───────────

    public function test_the_confirmation_says_a_standard_fitting_is_a_starting_point(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
            'requires_measurement_confirmation' => false,
        ]);

        $this->actingAs(User::find($order->user_id), 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('starting point')
            ->assertSee('not a guarantee of an exact fit')
            ->assertSee('we will cut to your measurements instead');
    }

    public function test_the_confirmation_says_the_frock_is_cut_to_the_customers_measurements(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'sizing_method' => CustomOrder::SIZING_MEASUREMENTS,
            'requires_measurement_confirmation' => false,
        ]);
        $order->measurements()->create([
            'measurement_type' => 'chest',
            'measurement_value' => 55,
            'measurement_unit' => 'cm',
        ]);

        $this->actingAs(User::find($order->user_id), 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            // Cut to the measurements, and the starting point wording does not apply.
            ->assertSee('We will cut it to these measurements')
            ->assertDontSee('not a guarantee of an exact fit');
    }

    public function test_the_snapshot_records_the_figures_the_frock_is_cut_from(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $this->confirm($order)->assertSessionHasNoErrors();

        $snapshot = $order->fresh()->sizing_snapshot;

        // The basis is the standard size, so the chart figures are what we cut from.
        $this->assertSame(CustomOrder::SIZING_STANDARD, $snapshot['production_basis']);
        $this->assertCount(1, $snapshot['production_figures']);
        $this->assertEquals(54, $snapshot['production_figures'][0]['min']);
    }

    public function test_the_snapshot_does_not_claim_chart_figures_when_measurements_are_the_basis(): void
    {
        $this->vocabulary();
        $chart = $this->chart();
        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'sizing_method' => CustomOrder::SIZING_MEASUREMENTS,
        ]);
        $order->measurements()->create([
            'measurement_type' => 'chest',
            'measurement_value' => 55,
            'measurement_unit' => 'cm',
        ]);

        $this->confirm($order)->assertSessionHasNoErrors();

        $snapshot = $order->fresh()->sizing_snapshot;

        // The chart figures are only what was shown; the customer's figures are the basis.
        $this->assertSame(CustomOrder::SIZING_MEASUREMENTS, $snapshot['production_basis']);
        $this->assertNull($snapshot['production_figures']);
        $this->assertCount(1, $snapshot['production_measurements']);
        $this->assertEquals(55.0, $snapshot['production_measurements'][0]['value']);
    }
}
