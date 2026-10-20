<?php

namespace Tests\Feature;

use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use Tests\TestCase;

/**
 * The public size guide, now that a size is chosen by age band and then by fitting.
 *
 * Three things are being protected. The customer is only ever offered fittings that
 * genuinely exist - a missing tier must not appear as a choice that could have been
 * made. A standard cut stays a legitimate option and is never given a tier it did
 * not have. And no figure is ever shown that the tailor did not record.
 */
class SizeGuideFitSelectionTest extends TestCase
{
    private function chart(array $overrides = []): SizeChart
    {
        return SizeChart::create(array_merge([
            'name' => 'Girls Frocks',
            'default_basis' => 'body',
        ], $overrides));
    }

    /**
     * @param  array<int, array{0: string, 1: string|null, 2: array<int, array<string, mixed>>}>  $sizes
     */
    private function chartWithSizes(array $sizes): SizeChart
    {
        $chart = $this->chart();
        $sort = 1;

        foreach ($sizes as [$label, $fit, $measurements]) {
            $entry = SizeChartEntry::create([
                'size_chart_id' => $chart->id,
                'label' => $label,
                'fit' => $fit,
                'sort_order' => $sort++,
            ]);

            foreach ($measurements as $m) {
                SizeChartMeasurement::create(array_merge([
                    'size_chart_entry_id' => $entry->id,
                    'unit' => 'cm',
                ], $m));
            }
        }

        $chart->load('entries.measurements');
        $chart->publish();

        return $chart;
    }

    private function chest(int $min, int $max, string $basis = 'body'): array
    {
        return [['measurement_type' => 'chest', 'basis' => $basis, 'min_value' => $min, 'max_value' => $max]];
    }

    /**
     * The fitting buttons offered for one age band, mapped to whether each is
     * disabled. Read from the rendered markup so the assertion is about what a
     * customer is actually offered.
     *
     * @return array<string, bool>
     */
    private function fitButtonsForBand(string $html, int $band): array
    {
        // Each fitting step block starts with this marker, so splitting on it
        // isolates one band at a time. The marker does not occur in the script.
        $parts = explode('data-sg-fit-step data-band="', $html);
        array_shift($parts);

        $block = $parts[$band] ?? '';

        preg_match_all('/<button\b[^>]*>/s', $block, $buttons);

        $offered = [];

        foreach ($buttons[0] as $button) {
            if (preg_match('/data-sg-fit-btn-fit="([^"]+)"/', $button, $match)) {
                $offered[$match[1]] = str_contains($button, 'disabled');
            }
        }

        return $offered;
    }

    private function fittingStepCount(string $html): int
    {
        return count(explode('data-sg-fit-step data-band="', $html)) - 1;
    }

    // ── step one: the age band ─────────────────────────────────────────────

    public function test_the_age_bands_offered_are_the_ones_the_chart_has(): void
    {
        $this->chartWithSizes([
            ['2-3 years', 'small', $this->chest(54, 56)],
            ['2-3 years', 'medium', $this->chest(56, 58)],
            ['4-5 years', 'small', $this->chest(58, 60)],
        ]);

        $response = $this->get(route('shop.size-guide'))->assertOk();

        $response->assertSee('choose an age band');
        $response->assertSee('2-3 years');
        $response->assertSee('4-5 years');
    }

    public function test_a_missing_fitting_is_not_offered_as_a_choice(): void
    {
        // Only small and large are offered at this age.
        $this->chartWithSizes([
            ['2-3 years', 'small', $this->chest(54, 56)],
            ['2-3 years', 'large', $this->chest(60, 62)],
        ]);

        $response = $this->get(route('shop.size-guide'))->assertOk();

        $response->assertSee('Small fitting');
        $response->assertSee('Large fitting');
        // Medium fitting is simply absent, not offered as an option.
        $response->assertDontSee('Medium fitting');
    }

    // ── step two: the fitting ──────────────────────────────────────────────

    public function test_only_the_fittings_offered_for_the_selected_band_are_listed(): void
    {
        $this->chartWithSizes([
            ['2-3 years', 'small', $this->chest(54, 56)],
            ['2-3 years', 'large', $this->chest(60, 62)],
            ['4-5 years', 'medium', $this->chest(64, 66)],
            ['6-7 years', 'medium', $this->chest(68, 70)],
        ]);

        $html = $this->get(route('shop.size-guide'))->assertOk()->getContent();

        // The first band offers two fittings, the second only one.
        $this->assertSame(['small' => false, 'large' => false], $this->fitButtonsForBand($html, 0));
        $this->assertSame(['medium' => false], $this->fitButtonsForBand($html, 1));

        // The second band must not inherit the first band's fittings.
        $this->assertArrayNotHasKey('small', $this->fitButtonsForBand($html, 1));
        $this->assertArrayNotHasKey('large', $this->fitButtonsForBand($html, 1));
    }

    public function test_a_band_of_only_standard_cuts_gets_no_fitting_step(): void
    {
        $this->chartWithSizes([
            ['2-3 years', null, $this->chest(54, 56)],
        ]);

        $response = $this->get(route('shop.size-guide'))->assertOk();

        // No phantom fitting step for a band that has nothing to choose.
        $this->assertSame(0, $this->fittingStepCount($response->getContent()));
        $response->assertDontSee('choose a fitting');
    }

    public function test_a_standard_cut_is_identified_as_having_no_tier(): void
    {
        $this->chartWithSizes([
            ['2-3 years', null, $this->chest(54, 56)],
        ]);

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('Standard (no tier assigned)')
            ->assertSee('2-3 years');
    }

    public function test_a_band_may_offer_a_standard_cut_alongside_a_tier(): void
    {
        $this->chartWithSizes([
            ['2-3 years', null, $this->chest(54, 56)],
            ['2-3 years', 'medium', $this->chest(56, 58)],
        ]);

        $response = $this->get(route('shop.size-guide'))->assertOk();

        $response->assertSee('choose a fitting');
        $response->assertSee('Medium fitting');
        $response->assertSee('Standard (no tier assigned)');
    }

    // ── combined labels ────────────────────────────────────────────────────

    public function test_a_tiered_size_is_labelled_with_its_age_band_and_fitting(): void
    {
        $this->chartWithSizes([
            ['2-3 years', 'medium', $this->chest(56, 58)],
        ]);

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('2-3 years · Medium fitting');
    }

    public function test_a_standard_cut_is_labelled_with_its_age_band_alone(): void
    {
        $this->chartWithSizes([
            ['2-3 years', null, $this->chest(54, 56)],
        ]);

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('2-3 years')
            ->assertDontSee('2-3 years · Standard');
    }

    // ── figures are never invented ─────────────────────────────────────────

    public function test_only_stored_figures_are_shown(): void
    {
        $this->chartWithSizes([
            ['2-3 years', 'small', $this->chest(54, 56)],
            ['2-3 years', 'medium', $this->chest(56, 58)],
        ]);

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('54–56 cm')
            ->assertSee('56–58 cm')
            ->assertDontSee('58–60 cm');
    }

    public function test_a_fitting_without_figures_for_this_basis_is_not_selectable(): void
    {
        // The chart carries garment figures only, so there is nothing to show for
        // the body basis and no body card is offered at all.
        $this->chartWithSizes([
            ['2-3 years', 'small', $this->chest(54, 56, 'finished_garment')],
        ]);

        $response = $this->get(route('shop.size-guide'))->assertOk();

        $response->assertSee('Finished garment');
        $response->assertDontSee('Body measurements');
    }

    public function test_an_unavailable_fitting_is_shown_but_cannot_be_chosen(): void
    {
        // A chart with both bases: one fitting has body figures, the other does not.
        $this->chartWithSizes([
            ['2-3 years', 'small', $this->chest(54, 56, 'body')],
            ['2-3 years', 'large', $this->chest(60, 62, 'finished_garment')],
        ]);

        $html = $this->get(route('shop.size-guide'))->assertOk()->getContent();

        // In the body view, the large fitting has no figures and is not selectable.
        $bodyBand = $this->fitButtonsForBand($html, 0);

        $this->assertArrayHasKey('small', $bodyBand);
        $this->assertArrayHasKey('large', $bodyBand);
        $this->assertFalse($bodyBand['small']);
        $this->assertTrue($bodyBand['large']);

        $this->assertStringContainsString('no body figures yet', $html);
    }

    // ── the guide still behaves as a guide ─────────────────────────────────

    public function test_an_empty_guide_is_unchanged(): void
    {
        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('being prepared')
            ->assertDontSee('cm')
            ->assertDontSee('choose an age band');
    }

    public function test_a_draft_chart_is_not_shown(): void
    {
        $chart = $this->chart();
        $entry = SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => '2-3 years',
            'fit' => 'small',
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

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertDontSee('Girls Frocks');
    }
}
