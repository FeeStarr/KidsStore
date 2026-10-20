<?php

namespace Tests\Feature;

use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-side fit tiers: what the tailor may select, what the editor refuses, and what
 * publication actually requires.
 *
 * Two ideas are being defended here. An untilered size is a real state - it is the
 * existing standard cut - and must never be quietly turned into "medium". And coverage
 * is per age band: offering two fittings for one band is correct, so a missing third
 * is not a defect.
 */
class FitTierAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function chart(): SizeChart
    {
        return SizeChart::create([
            'name' => 'Girls Frocks 2026',
            'default_basis' => 'finished_garment',
            'version' => 1,
            'is_active' => true,
        ]);
    }

    private function entry(SizeChart $chart, string $label = '2-3 years', ?string $fit = null, bool $withFigures = false): SizeChartEntry
    {
        $entry = SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => $label,
            'fit' => $fit,
            'sort_order' => 1,
        ]);

        if ($withFigures) {
            SizeChartMeasurement::create([
                'size_chart_entry_id' => $entry->id,
                'measurement_type' => 'chest',
                'basis' => 'body',
                'min_value' => 54,
                'max_value' => 58,
                'unit' => 'cm',
            ]);
        }

        return $entry;
    }

    private function asAdmin()
    {
        return $this->actingAs($this->admin(), 'admin');
    }

    // ------------------------------------------------------- fit can be selected

    public function test_a_size_can_be_added_with_no_fitting_assigned(): void
    {
        $chart = $this->chart();

        $this->asAdmin()
            ->post(route('admin.size-charts.entries.store', $chart), ['label' => '2-3 years'])
            ->assertRedirect();

        $this->assertNull(SizeChartEntry::where('label', '2-3 years')->first()->fit);
    }

    public function test_an_empty_selection_never_becomes_medium(): void
    {
        $chart = $this->chart();

        // The literal empty string an <option value=""> submits.
        $this->asAdmin()
            ->post(route('admin.size-charts.entries.store', $chart), ['label' => '4-5 years', 'fit' => ''])
            ->assertRedirect();

        $entry = SizeChartEntry::where('label', '4-5 years')->first();

        $this->assertNotSame('medium', $entry->fit);
        $this->assertNull($entry->fit);
    }

    public function test_each_fitting_can_be_selected_when_adding_a_size(): void
    {
        $chart = $this->chart();

        foreach (SizeChart::FITS as $fit) {
            $this->asAdmin()->post(route('admin.size-charts.entries.store', $chart), [
                'label' => '6-7 years',
                'fit' => $fit,
            ])->assertRedirect();
        }

        $this->assertSame(
            SizeChart::FITS,
            SizeChartEntry::where('label', '6-7 years')->pluck('fit')->all()
        );
    }

    public function test_an_unknown_fitting_is_rejected(): void
    {
        $chart = $this->chart();

        $this->asAdmin()
            ->post(route('admin.size-charts.entries.store', $chart), [
                'label' => '2-3 years',
                'fit' => 'enormous',
            ])
            ->assertSessionHasErrors('fit');

        $this->assertSame(0, SizeChartEntry::count());
    }

    public function test_a_fitting_can_be_assigned_to_an_existing_size(): void
    {
        $chart = $this->chart();
        $entry = $this->entry($chart);

        $this->asAdmin()
            ->put(route('admin.size-charts.entries.update', [$chart, $entry]), [
                'label' => '2-3 years',
                'fit' => 'large',
            ])
            ->assertRedirect();

        $this->assertSame('large', $entry->fresh()->fit);
    }

    public function test_a_fitting_can_be_removed_from_a_size(): void
    {
        $chart = $this->chart();
        $entry = $this->entry($chart, '2-3 years', 'small');

        $this->asAdmin()
            ->put(route('admin.size-charts.entries.update', [$chart, $entry]), [
                'label' => '2-3 years',
                'fit' => '',
            ])
            ->assertRedirect();

        $this->assertNull($entry->fresh()->fit);
    }

    // ------------------------------------------------- untilered uniqueness

    public function test_two_untilered_sizes_may_not_share_a_label(): void
    {
        $chart = $this->chart();
        $this->entry($chart, '2-3 years');

        $this->asAdmin()
            ->post(route('admin.size-charts.entries.store', $chart), ['label' => '2-3 years'])
            ->assertSessionHasErrors('fit');

        $this->assertSame(1, SizeChartEntry::count());
    }

    public function test_the_same_label_may_be_used_on_a_different_chart(): void
    {
        $chart = $this->chart();
        $other = $this->chart();
        $this->entry($chart, '2-3 years');

        $this->asAdmin()
            ->post(route('admin.size-charts.entries.store', $other), ['label' => '2-3 years'])
            ->assertRedirect();

        $this->assertSame(2, SizeChartEntry::where('label', '2-3 years')->count());
    }

    public function test_a_size_can_be_saved_without_conflicting_with_itself(): void
    {
        $chart = $this->chart();
        $entry = $this->entry($chart, '2-3 years');

        // Editing code only must not look like a duplicate label.
        $this->asAdmin()
            ->put(route('admin.size-charts.entries.update', [$chart, $entry]), [
                'label' => '2-3 years',
                'code' => '2-3Y',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('2-3Y', $entry->fresh()->code);
    }

    public function test_a_renamed_untilered_size_may_not_take_a_label_already_in_use(): void
    {
        $chart = $this->chart();
        $first = $this->entry($chart, '2-3 years');
        $this->entry($chart, '4-5 years');

        $this->asAdmin()
            ->put(route('admin.size-charts.entries.update', [$chart, $first]), ['label' => '4-5 years'])
            ->assertSessionHasErrors('fit');

        $this->assertSame('2-3 years', $first->fresh()->label);
    }

    public function test_an_untilered_and_a_tiered_size_may_share_a_label(): void
    {
        $chart = $this->chart();
        $this->entry($chart, '2-3 years');

        // Partial coverage at one age band is legitimate.
        $this->asAdmin()
            ->post(route('admin.size-charts.entries.store', $chart), [
                'label' => '2-3 years',
                'fit' => 'medium',
            ])
            ->assertRedirect();

        $this->assertSame(2, SizeChartEntry::where('label', '2-3 years')->count());
    }

    // ------------------------------------------------------------- publication

    public function test_a_missing_fitting_never_blocks_publication(): void
    {
        $chart = $this->chart();
        $this->entry($chart, '2-3 years', 'small', true);
        $this->entry($chart, '2-3 years', 'medium', true);

        // Large is simply not offered at this age. That is not a defect.
        $this->assertSame([], $chart->fresh()->publicationBlockers());
    }

    public function test_an_offered_fitting_with_no_figures_blocks_publication(): void
    {
        $chart = $this->chart();
        $this->entry($chart, '2-3 years', 'small', true);
        $this->entry($chart, '2-3 years', 'large', false);

        $blockers = $chart->fresh()->publicationBlockers();

        $this->assertCount(1, $blockers);
        $this->assertStringContainsString('Large fitting', $blockers[0]);
    }

    public function test_an_untilered_size_with_no_figures_still_blocks_publication(): void
    {
        $chart = $this->chart();
        $this->entry($chart, '2-3 years', null, false);

        $this->assertNotSame([], $chart->fresh()->publicationBlockers());
    }

    public function test_range_and_completeness_checks_are_unchanged(): void
    {
        $chart = $this->chart();
        $entry = $this->entry($chart, '2-3 years', 'medium', true);
        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'waist',
            'basis' => 'body',
            'min_value' => 70,
            'max_value' => 50,
            'unit' => 'cm',
        ]);

        $blockers = $chart->fresh()->publicationBlockers();

        $this->assertStringContainsString('minimum above its maximum', $blockers[0]);
    }

    public function test_a_chart_with_no_sizes_is_still_blocked(): void
    {
        $this->assertNotSame([], $this->chart()->publicationBlockers());
    }

    // --------------------------------------------------------- coverage matrix

    public function test_coverage_is_grouped_by_age_band(): void
    {
        $chart = $this->chart();
        $this->entry($chart, '2-3 years', 'small', true);
        $this->entry($chart, '2-3 years', 'large', false);
        $this->entry($chart, '4-5 years', 'medium', true);

        $coverage = $chart->fresh()->fitCoverage();

        $this->assertSame(['2-3 years', '4-5 years'], array_keys($coverage));
        $this->assertTrue($coverage['2-3 years']['small']['complete']);
        $this->assertFalse($coverage['2-3 years']['large']['complete']);
        $this->assertArrayNotHasKey('medium', $coverage['2-3 years']);
        $this->assertTrue($coverage['4-5 years']['medium']['complete']);
    }

    public function test_the_editor_shows_coverage_and_the_unassigned_option(): void
    {
        $chart = $this->chart();
        $this->entry($chart, '2-3 years', 'small', true);

        $this->asAdmin()
            ->get(route('admin.size-charts.show', $chart))
            ->assertOk()
            ->assertSee('Fitting coverage')
            ->assertSee('no tier assigned')
            ->assertSee('Offered');
    }

    // ------------------------------------------------------------ authorisation

    public function test_a_non_admin_cannot_add_a_size(): void
    {
        $chart = $this->chart();

        // The admin permission middleware redirects rather than aborting.
        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]))
            ->post(route('admin.size-charts.entries.store', $chart), ['label' => '2-3 years'])
            ->assertRedirect();

        $this->assertSame(0, SizeChartEntry::count());
    }
}
