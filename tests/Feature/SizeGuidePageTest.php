<?php

namespace Tests\Feature;

use App\Models\CustomOrderMeasurementGuide;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use Tests\TestCase;

/**
 * The public size guide.
 *
 * The fairness point this protects: body and finished-garment figures must never be
 * presented as interchangeable. A parent who measures their child's chest against a
 * finished-garment number will pick the wrong size, and that dispute is the reason the
 * basis distinction exists.
 */
class SizeGuidePageTest extends TestCase
{
    private function chart(array $overrides = []): SizeChart
    {
        return SizeChart::create(array_merge([
            'name' => 'Girls Frocks',
            'default_basis' => 'finished_garment',
        ], $overrides));
    }

    private function entryWith(SizeChart $chart, array $measurements, string $label = '2-3 years'): SizeChartEntry
    {
        $entry = SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => $label,
            'sort_order' => 1,
        ]);

        foreach ($measurements as $m) {
            SizeChartMeasurement::create(array_merge([
                'size_chart_entry_id' => $entry->id,
                'unit' => 'cm',
            ], $m));
        }

        return $entry;
    }

    private function publish(SizeChart $chart): SizeChart
    {
        $chart->load('entries.measurements');
        $chart->publish();

        return $chart;
    }

    private function seedGuides(): void
    {
        CustomOrderMeasurementGuide::create([
            'measurement_type' => 'chest',
            'basis' => 'body',
            'name' => 'Chest / Bust',
            'description' => 'Measure around the fullest part of the child chest.',
        ]);
        CustomOrderMeasurementGuide::create([
            'measurement_type' => 'chest',
            'basis' => 'finished_garment',
            'name' => 'Chest / Bust (garment)',
            'description' => 'Lay the garment flat and measure straight across the widest part.',
        ]);
    }

    // ── availability ──────────────────────────────────────────────────────

    public function test_the_guide_is_publicly_reachable(): void
    {
        $this->get(route('shop.size-guide'))->assertOk();
    }

    public function test_an_empty_guide_explains_itself_without_inventing_figures(): void
    {
        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('being prepared')
            // The unit heading is what a chart with figures would add. Asserting on
            // 'cm' instead would also match the random CSRF token, which makes the
            // test fail now and then for no reason at all.
            ->assertDontSee('in centimetres');
    }

    public function test_draft_charts_are_not_shown_to_customers(): void
    {
        $chart = $this->chart();
        $this->entryWith($chart, [
            ['measurement_type' => 'chest', 'basis' => 'body', 'min_value' => 54, 'max_value' => 56],
        ]);

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertDontSee('Girls Frocks');
    }

    public function test_a_published_chart_is_shown(): void
    {
        $chart = $this->chart();
        $this->entryWith($chart, [
            ['measurement_type' => 'chest', 'basis' => 'body', 'min_value' => 54, 'max_value' => 56],
        ]);
        $this->publish($chart);

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('Girls Frocks')
            ->assertSee('2-3 years')
            ->assertSee('54–56 cm');
    }

    // ── basis separation ──────────────────────────────────────────────────

    public function test_a_finished_garment_only_chart_never_mentions_body_measurements(): void
    {
        $chart = $this->chart(['default_basis' => 'finished_garment']);
        $this->entryWith($chart, [
            ['measurement_type' => 'chest', 'basis' => 'finished_garment', 'min_value' => 60, 'max_value' => 62],
        ]);
        $this->publish($chart);

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('Finished garment')
            // The body explanation must not appear, because no body figures exist.
            ->assertDontSee('Body measurements')
            ->assertDontSee('measure your child');
    }

    public function test_a_body_only_chart_never_mentions_finished_garment(): void
    {
        $chart = $this->chart(['default_basis' => 'body']);
        $this->entryWith($chart, [
            ['measurement_type' => 'chest', 'basis' => 'body', 'min_value' => 54, 'max_value' => 56],
        ]);
        $this->publish($chart);

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('Body measurements')
            ->assertDontSee('Finished garment');
    }

    public function test_a_chart_with_both_datasets_labels_them_separately(): void
    {
        $chart = $this->chart();
        $this->entryWith($chart, [
            ['measurement_type' => 'chest', 'basis' => 'body', 'min_value' => 54, 'max_value' => 56],
            ['measurement_type' => 'chest', 'basis' => 'finished_garment', 'min_value' => 60, 'max_value' => 62],
        ]);
        $this->publish($chart);

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('Body measurements')
            ->assertSee('Finished garment')
            // Both figures appear, each in its own table.
            ->assertSee('54–56 cm')
            ->assertSee('60–62 cm');
    }

    public function test_how_to_measure_copy_matches_the_basis_of_the_figures(): void
    {
        $this->seedGuides();

        $chart = $this->chart(['default_basis' => 'body']);
        $this->entryWith($chart, [
            ['measurement_type' => 'chest', 'basis' => 'body', 'min_value' => 54, 'max_value' => 56],
        ]);
        $this->publish($chart);

        $this->get(route('shop.size-guide'))
            ->assertOk()
            // The body instruction appears; the garment one must not, since no
            // garment figures are shown.
            ->assertSee('fullest part of the child chest')
            ->assertDontSee('Lay the garment flat');
    }

    public function test_garment_instructions_appear_only_alongside_garment_figures(): void
    {
        $this->seedGuides();

        $chart = $this->chart(['default_basis' => 'finished_garment']);
        $this->entryWith($chart, [
            ['measurement_type' => 'chest', 'basis' => 'finished_garment', 'min_value' => 60, 'max_value' => 62],
        ]);
        $this->publish($chart);

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('Lay the garment flat')
            ->assertDontSee('fullest part of the child chest');
    }

    public function test_a_missing_figure_renders_as_a_dash_not_a_guess(): void
    {
        $chart = $this->chart();
        $entry = $this->entryWith($chart, [
            ['measurement_type' => 'chest', 'basis' => 'body', 'min_value' => 54, 'max_value' => 56],
            ['measurement_type' => 'waist', 'basis' => 'body', 'min_value' => null, 'max_value' => null],
        ]);
        // A second size carrying only one of the two types.
        $other = SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => '4-5 years',
            'sort_order' => 2,
        ]);
        SizeChartMeasurement::create([
            'size_chart_entry_id' => $other->id,
            'measurement_type' => 'chest',
            'basis' => 'body',
            'min_value' => 58,
            'max_value' => 60,
        ]);
        $this->publish($chart);

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('54–56 cm')
            ->assertSee('58–60 cm')
            ->assertSee('—');
    }
}
