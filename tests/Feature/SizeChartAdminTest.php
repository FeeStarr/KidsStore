<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderMeasurementField;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use App\Models\User;
use Tests\TestCase;

/**
 * Admin CRUD for size charts.
 *
 * The rules that matter: a chart cannot be published half-built, a version locked by an
 * order cannot be edited, and the same measurement type may appear once per basis so a
 * chart can hold body and finished-garment figures side by side.
 */
class SizeChartAdminTest extends TestCase
{
    private function admin(): User
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->seedVocabulary();

        return $admin;
    }

    private function seedVocabulary(): void
    {
        foreach ([['chest', 'Chest / Bust'], ['waist', 'Waist']] as $i => [$name, $label]) {
            CustomOrderMeasurementField::firstOrCreate(
                ['name' => $name],
                ['label' => $label, 'sort_order' => $i + 1, 'is_active' => true]
            );
        }
    }

    private function chart(array $overrides = []): SizeChart
    {
        return SizeChart::create(array_merge([
            'name' => 'Girls Frocks',
            'default_basis' => 'finished_garment',
        ], $overrides));
    }

    private function entry(SizeChart $chart, string $label = '2-3 years'): SizeChartEntry
    {
        return SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => $label,
            'sort_order' => 1,
        ]);
    }

    // ── index / create ────────────────────────────────────────────────────

    public function test_index_lists_charts(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.size-charts.index'))
            ->assertOk()
            ->assertSee('Girls Frocks')
            ->assertSee('Draft');
    }

    public function test_a_chart_can_be_created(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.size-charts.store'), [
                'name' => 'Boys 2026',
                'default_basis' => 'body',
            ])
            ->assertRedirect();

        $chart = SizeChart::where('name', 'Boys 2026')->firstOrFail();

        $this->assertSame('body', $chart->default_basis);
        $this->assertSame(1, $chart->version);
        $this->assertFalse($chart->is_published);
    }

    public function test_chart_name_is_required(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.size-charts.store'), ['default_basis' => 'body'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, SizeChart::count());
    }

    public function test_an_invalid_basis_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.size-charts.store'), [
                'name' => 'Bad basis',
                'default_basis' => 'invented',
            ])
            ->assertSessionHasErrors('default_basis');
    }

    // ── entries and measurements ──────────────────────────────────────────

    public function test_a_size_can_be_added(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.size-charts.entries.store', $chart), ['label' => '2-3 years'])
            ->assertRedirect();

        $this->assertDatabaseHas('size_chart_entries', [
            'size_chart_id' => $chart->id,
            'label' => '2-3 years',
        ]);
    }

    public function test_a_measurement_inherits_the_chart_default_basis(): void
    {
        $admin = $this->admin();
        $chart = $this->chart(['default_basis' => 'finished_garment']);
        $entry = $this->entry($chart);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.size-charts.measurements.store', [$chart, $entry]), [
                'measurement_type' => 'chest',
                'min_value' => 60,
                'max_value' => 62,
                'unit' => 'cm',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('size_chart_measurements', [
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'finished_garment',
            'min_value' => 60,
        ]);
    }

    public function test_an_explicit_basis_overrides_the_chart_default(): void
    {
        $admin = $this->admin();
        $chart = $this->chart(['default_basis' => 'finished_garment']);
        $entry = $this->entry($chart);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.size-charts.measurements.store', [$chart, $entry]), [
                'measurement_type' => 'chest',
                'basis' => 'body',
                'min_value' => 54,
                'max_value' => 56,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('size_chart_measurements', [
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'body',
        ]);
    }

    public function test_the_same_type_may_be_added_twice_with_different_bases(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();
        $entry = $this->entry($chart);

        foreach ([['body', 54, 56], ['finished_garment', 60, 62]] as [$basis, $min, $max]) {
            $this->actingAs($admin, 'admin')
                ->post(route('admin.size-charts.measurements.store', [$chart, $entry]), [
                    'measurement_type' => 'chest',
                    'basis' => $basis,
                    'min_value' => $min,
                    'max_value' => $max,
                ])
                ->assertRedirect();
        }

        $this->assertSame(2, SizeChartMeasurement::where('size_chart_entry_id', $entry->id)->count());
    }

    public function test_an_unknown_measurement_type_is_rejected(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();
        $entry = $this->entry($chart);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.size-charts.measurements.store', [$chart, $entry]), [
                'measurement_type' => 'horn_length',
            ])
            ->assertSessionHasErrors('measurement_type');
    }

    public function test_a_maximum_below_the_minimum_is_rejected(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();
        $entry = $this->entry($chart);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.size-charts.measurements.store', [$chart, $entry]), [
                'measurement_type' => 'chest',
                'min_value' => 70,
                'max_value' => 60,
            ])
            ->assertSessionHasErrors('max_value');
    }

    public function test_an_entry_cannot_be_edited_through_another_chart(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();
        $other = $this->chart(['name' => 'Other']);
        $entry = $this->entry($other);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.size-charts.entries.update', [$chart, $entry]), ['label' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_removing_an_entry_removes_its_measurements(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();
        $entry = $this->entry($chart);

        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'body',
            'min_value' => 54,
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.size-charts.entries.destroy', [$chart, $entry]))
            ->assertRedirect();

        $this->assertSame(0, SizeChartMeasurement::where('size_chart_entry_id', $entry->id)->count());
    }

    // ── publication ───────────────────────────────────────────────────────

    public function test_publishing_is_refused_while_the_chart_is_incomplete(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.size-charts.show', $chart))
            ->post(route('admin.size-charts.publish', $chart))
            ->assertStatus(500);

        $this->assertFalse($chart->fresh()->is_published);
    }

    public function test_a_complete_chart_publishes(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();
        $entry = $this->entry($chart);
        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'finished_garment',
            'min_value' => 60,
            'max_value' => 62,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.size-charts.publish', $chart))
            ->assertRedirect();

        $chart->refresh();
        $this->assertTrue($chart->is_published);
        $this->assertNotNull($chart->published_at);
    }

    public function test_retiring_withdraws_the_chart_from_customers(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();
        $chart->update(['is_published' => true, 'published_at' => now()]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.size-charts.retire', $chart))
            ->assertRedirect();

        $chart->refresh();
        $this->assertFalse($chart->is_active);
        $this->assertFalse($chart->is_published);
        $this->assertSame(0, SizeChart::published()->count());
    }

    // ── locking ───────────────────────────────────────────────────────────

    public function test_a_locked_chart_refuses_further_edits(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();
        $entry = $this->entry($chart);

        $order = CustomOrder::create([
            'custom_order_number' => 'CO-LOCK-'.uniqid(),
            'user_id' => $this->customer()->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_QUOTED,
            'child_name' => 'Amara',
            'total_amount' => 15000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ]);

        $chart->lockFor($order);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.size-charts.update', $chart), ['name' => 'Renamed'])
            ->assertStatus(500);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.size-charts.entries.store', $chart), ['label' => '4-5 years'])
            ->assertStatus(500);

        $this->assertSame('Girls Frocks', $chart->fresh()->name);
        $this->assertSame(0, $chart->entries()->where('label', '4-5 years')->count());
    }

    public function test_a_locked_chart_can_still_be_copied_forward(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();
        $entry = $this->entry($chart);
        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'body',
            'min_value' => 54,
            'max_value' => 56,
        ]);

        $order = CustomOrder::create([
            'custom_order_number' => 'CO-LOCK-'.uniqid(),
            'user_id' => $this->customer()->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_QUOTED,
            'child_name' => 'Amara',
            'total_amount' => 15000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ]);
        $chart->lockFor($order);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.size-charts.new-version', $chart))
            ->assertRedirect();

        $v2 = SizeChart::where('version', 2)->firstOrFail();

        $this->assertNotSame($chart->id, $v2->id);
        $this->assertFalse($v2->is_locked);
        $this->assertSame(1, $v2->entries()->count());
        $this->assertSame(1, $v2->entries->first()->measurements()->count());
    }

    public function test_the_editor_renders_with_sizes_measurements_and_no_blockers(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();
        $entry = $this->entry($chart);

        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'finished_garment',
            'min_value' => 60,
            'max_value' => 62,
            'unit' => 'cm',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.size-charts.show', $chart))
            ->assertOk()
            ->assertSee('2-3 years')
            ->assertSee('Chest / Bust')
            ->assertSee('Finished garment')
            // A complete chart must not show the not-ready warning.
            ->assertDontSee('Not ready to publish');
    }

    public function test_the_editor_reports_why_a_chart_is_not_publishable(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();

        // An entry exists but has no measurements at all.
        $this->entry($chart);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.size-charts.show', $chart))
            ->assertOk()
            ->assertSee('Not ready to publish')
            ->assertSee('has no measurements');
    }

    public function test_the_editor_renders_for_a_locked_chart_without_edit_controls(): void
    {
        $admin = $this->admin();
        $chart = $this->chart();
        $this->entry($chart);

        $chart->lockFor($this->lockedOrder());

        $this->actingAs($admin, 'admin')
            ->get(route('admin.size-charts.show', $chart))
            ->assertOk()
            ->assertSee('locked because an order was sized against it')
            ->assertDontSee('Add a size', false);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
    }

    private function lockedOrder(): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => 'CO-LOCK-'.uniqid(),
            'user_id' => $this->customer()->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_QUOTED,
            'child_name' => 'Amara',
            'total_amount' => 15000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ]);
    }
}
