<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderMeasurementField;
use App\Models\CustomOrderMeasurementGuide;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How-to-measure copy is global. These rows carry no size_chart_id, so an edit made
 * while looking at one chart is seen by customers on all of them.
 *
 * That is convenient - one correction fixes the whole shop - and dangerous if it is
 * not made obvious. So these tests pin two things: the blast radius is stated from the
 * database rather than believed from the form, and the panel stays editable even when
 * a chart is locked, because a lock protects measurements and says nothing about
 * whether an instruction is correct.
 */
class MeasurementGuideAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    private function asAdmin()
    {
        return $this->actingAs($this->admin(), 'admin');
    }

    /**
     * The editor lists guides by iterating the active measurement fields, which are
     * seeded data that does not exist in a test database.
     */
    private function seedFields(array $names = ['chest', 'waist', 'hip']): void
    {
        foreach ($names as $i => $name) {
            CustomOrderMeasurementField::create([
                'name' => $name,
                'label' => ucfirst(str_replace('_', ' ', $name)),
                'is_active' => true,
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function chart(string $name = 'Girls Frocks 2026'): SizeChart
    {
        return SizeChart::create([
            'name' => $name,
            'default_basis' => 'body',
            'version' => 1,
            'is_active' => true,
        ]);
    }

    private function guide(string $type = 'chest', string $basis = 'body', array $overrides = []): CustomOrderMeasurementGuide
    {
        return CustomOrderMeasurementGuide::create(array_merge([
            'measurement_type' => $type,
            'basis' => $basis,
            'name' => ucfirst(str_replace('_', ' ', $type)),
            'description' => 'Original wording written by the assistant.',
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    private function chartUsing(string $chartName, string $type): SizeChart
    {
        $chart = $this->chart($chartName);
        $entry = SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => '2-3 years',
            'sort_order' => 1,
        ]);
        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => $type,
            'basis' => 'body',
            'min_value' => 54,
            'max_value' => 58,
            'unit' => 'cm',
        ]);

        return $chart;
    }

    // ---------------------------------------------------------------- editing

    public function test_a_guide_can_be_edited(): void
    {
        $chart = $this->chart();
        $guide = $this->guide();

        $this->asAdmin()
            ->put(route('admin.size-charts.guides.update', [$chart, $guide]), [
                'name' => 'Chest (measured over a layer)',
                'description' => 'Measure around the fullest part, over a light layer.',
                'is_active' => '1',
                'confirm_global_change' => 'on',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $guide->refresh();

        $this->assertSame('Chest (measured over a layer)', $guide->name);
        $this->assertSame('Measure around the fullest part, over a light layer.', $guide->description);
        $this->assertTrue($guide->is_active);
    }

    public function test_a_guide_can_be_hidden_without_being_deleted(): void
    {
        $chart = $this->chart();
        $guide = $this->guide();

        $this->asAdmin()
            ->put(route('admin.size-charts.guides.update', [$chart, $guide]), [
                'name' => 'Chest',
                'description' => 'Still stored, just not offered.',
                'confirm_global_change' => 'on',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('custom_order_measurement_guides', [
            'id' => $guide->id,
            'is_active' => false,
        ]);
    }

    public function test_a_hidden_guide_stays_out_of_customer_facing_views(): void
    {
        $this->guide('chest', 'body');
        $this->guide('waist', 'body');
        $this->guide('chest', 'finished_garment');

        $hidden = $this->guide('hip', 'body', ['is_active' => false]);
        $this->guide('hip', 'finished_garment');

        $active = CustomOrderMeasurementGuide::active()->pluck('id');

        $this->assertFalse($active->contains($hidden->id));
        $this->assertDatabaseHas('custom_order_measurement_guides', ['id' => $hidden->id]);
    }

    public function test_the_editor_labels_each_basis_separately(): void
    {
        $chart = $this->chart();
        $this->seedFields(['chest']);
        $this->guide('chest', 'body', ['name' => 'Chest of the child']);
        $this->guide('chest', 'finished_garment', ['name' => 'Chest of the frock']);

        $this->asAdmin()
            ->get(route('admin.size-charts.show', $chart))
            ->assertOk()
            ->assertSee('Body measurements')
            ->assertSee('Finished garment measurements')
            ->assertSee('Chest of the child')
            ->assertSee('Chest of the frock');
    }

    public function test_the_global_scope_is_stated_on_the_page(): void
    {
        $chart = $this->chart();
        $this->seedFields(['chest']);
        $this->guide();

        $this->asAdmin()
            ->get(route('admin.size-charts.show', $chart))
            ->assertOk()
            ->assertSee('Global measurement instructions')
            ->assertSee('apply to all KidsFlairr size charts');
    }

    // ------------------------------------------------------------- confirmation

    public function test_an_unchecked_confirmation_is_refused(): void
    {
        $chart = $this->chart();
        $guide = $this->guide();

        $this->asAdmin()
            ->put(route('admin.size-charts.guides.update', [$chart, $guide]), [
                'name' => 'Changed',
                'description' => 'Changed too.',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('confirm_global_change');

        $this->assertNotSame('Changed', $guide->fresh()->name);
    }

    public function test_a_tampered_confirmation_is_refused(): void
    {
        $chart = $this->chart();
        $guide = $this->guide();

        // "required" alone would have accepted any non-empty string.
        $this->asAdmin()
            ->put(route('admin.size-charts.guides.update', [$chart, $guide]), [
                'name' => 'Changed',
                'description' => 'Changed too.',
                'is_active' => '1',
                'confirm_global_change' => 'no',
            ])
            ->assertSessionHasErrors('confirm_global_change');

        $this->assertNotSame('Changed', $guide->fresh()->name);
    }

    public function test_the_confirmation_cannot_be_supplied_as_zero(): void
    {
        $chart = $this->chart();
        $guide = $this->guide();

        $this->asAdmin()
            ->put(route('admin.size-charts.guides.update', [$chart, $guide]), [
                'name' => 'Changed',
                'description' => 'Changed too.',
                'confirm_global_change' => '0',
            ])
            ->assertSessionHasErrors('confirm_global_change');
    }

    // ------------------------------------------------- blast radius is truthful

    public function test_the_affected_chart_list_comes_from_the_database(): void
    {
        $chart = $this->chart();
        $this->chartUsing('Boys Frocks 2026', 'chest');
        $this->chartUsing('Toddler Sets 2026', 'chest');
        $guide = $this->guide('chest');

        $response = $this->asAdmin()
            ->put(route('admin.size-charts.guides.update', [$chart, $guide]), [
                'name' => 'Chest',
                'description' => 'Reworded.',
                'is_active' => '1',
                'confirm_global_change' => 'on',
            ])
            ->assertRedirect();

        $message = session('success');

        $this->assertStringContainsString('Boys Frocks 2026', $message);
        $this->assertStringContainsString('Toddler Sets 2026', $message);
    }

    public function test_a_submitted_chart_list_is_ignored(): void
    {
        $chart = $this->chart();
        $this->chartUsing('Boys Frocks 2026', 'chest');
        $guide = $this->guide('chest');

        // The browser claims only this chart is affected.
        $response = $this->asAdmin()
            ->put(route('admin.size-charts.guides.update', [$chart, $guide]), [
                'name' => 'Chest',
                'description' => 'Reworded.',
                'is_active' => '1',
                'confirm_global_change' => 'on',
                'affected_charts' => ['Girls Frocks 2026'],
            ])
            ->assertRedirect();

        $this->assertStringContainsString('Boys Frocks 2026', session('success'));
    }

    public function test_the_editor_lists_the_charts_using_a_measurement(): void
    {
        $chart = $this->chart();
        $this->seedFields(['chest']);
        $this->chartUsing('Boys Frocks 2026', 'chest');
        $this->guide('chest');

        $this->asAdmin()
            ->get(route('admin.size-charts.show', $chart))
            ->assertOk()
            ->assertSee('Also used by');
    }

    // ------------------------------------------------------------ chart locking

    public function test_a_guide_can_be_edited_while_the_chart_is_locked(): void
    {
        $chart = $this->chart();
        $guide = $this->guide();

        $this->lockChart($chart);

        $this->asAdmin()
            ->put(route('admin.size-charts.guides.update', [$chart, $guide]), [
                'name' => 'Corrected technique',
                'description' => 'The right way to do it.',
                'is_active' => '1',
                'confirm_global_change' => 'on',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Corrected technique', $guide->fresh()->name);
    }

    public function test_locking_a_chart_still_blocks_its_sizes_but_not_its_guides(): void
    {
        $chart = $this->chart();
        $entry = SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => '2-3 years',
            'sort_order' => 1,
        ]);
        $guide = $this->guide();

        $this->lockChart($chart);

        // The measurement side stays protected...
        $this->asAdmin()
            ->put(route('admin.size-charts.entries.update', [$chart, $entry]), ['label' => 'Renamed'])
            ->assertStatus(500);

        // ...while the global instruction remains correctable.
        $this->asAdmin()
            ->put(route('admin.size-charts.guides.update', [$chart, $guide]), [
                'name' => 'Still editable',
                'description' => 'Body wording.',
                'is_active' => '1',
                'confirm_global_change' => 'on',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2-3 years', $entry->fresh()->label);
        $this->assertSame('Still editable', $guide->fresh()->name);
    }

    // ------------------------------------------------ publication stays intact

    public function test_editing_a_guide_does_not_weaken_publication_checks(): void
    {
        $chart = $this->chart();
        $entry = SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => '2-3 years',
            'sort_order' => 1,
        ]);
        $guide = $this->guide();

        $this->assertNotSame([], $chart->fresh()->publicationBlockers());

        $this->asAdmin()
            ->put(route('admin.size-charts.guides.update', [$chart, $guide]), [
                'name' => 'Chest',
                'description' => 'Reworded.',
                'is_active' => '1',
                'confirm_global_change' => 'on',
            ])
            ->assertRedirect();

        // Still blocked, still for the same reason.
        $this->assertNotSame([], $chart->fresh()->publicationBlockers());
    }

    public function test_a_published_chart_is_not_unlocked_by_editing_a_guide(): void
    {
        $chart = $this->chart();
        $guide = $this->guide();

        $this->lockChart($chart);

        $this->asAdmin()
            ->put(route('admin.size-charts.guides.update', [$chart, $guide]), [
                'name' => 'Chest',
                'description' => 'Reworded.',
                'is_active' => '1',
                'confirm_global_change' => 'on',
            ])
            ->assertRedirect();

        $this->assertTrue($chart->fresh()->is_locked);
    }

    // ------------------------------------------------------------- permissions

    public function test_a_non_admin_cannot_edit_a_guide(): void
    {
        $chart = $this->chart();
        $guide = $this->guide();

        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]))
            ->put(route('admin.size-charts.guides.update', [$chart, $guide]), [
                'name' => 'Hijacked',
                'description' => 'Hijacked.',
                'confirm_global_change' => 'on',
            ])
            ->assertRedirect();

        $this->assertNotSame('Hijacked', $guide->fresh()->name);
    }

    private function lockChart(SizeChart $chart): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $order = CustomOrder::create([
            'custom_order_number' => 'CO-LOCK-'.uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_QUOTED,
            'child_name' => 'Amara',
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
            'sizing_method' => CustomOrder::SIZING_STANDARD,
        ]);

        $chart->lockFor($order);
    }
}
