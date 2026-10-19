<?php

namespace Tests\Feature;

use App\Models\CustomOrderMeasurementGuide;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

/**
 * A chart may legitimately list the same measurement twice for one size - once as a
 * body measurement and once as a finished-garment measurement. They are not
 * interchangeable, and conflating them is how a sizing dispute starts.
 */
class SizeChartSchemaTest extends TestCase
{
    private function chart(): SizeChart
    {
        return SizeChart::create([
            'name' => 'Girls Frocks 2026',
            'default_basis' => 'finished_garment',
            'version' => 1,
            'is_active' => true,
        ]);
    }

    private function entry(SizeChart $chart, string $label = '2-3 years', ?string $fit = null): SizeChartEntry
    {
        return SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => $label,
            'fit' => $fit,
            'sort_order' => 1,
        ]);
    }

    public function test_a_chart_defaults_to_a_finished_garment_basis(): void
    {
        $chart = $this->chart();

        $this->assertSame('finished_garment', $chart->default_basis);
        $this->assertSame(1, $chart->version);
        $this->assertFalse($chart->is_published);
        $this->assertFalse($chart->is_locked);
    }

    public function test_the_same_measurement_type_may_exist_once_per_basis(): void
    {
        $entry = $this->entry($this->chart());

        $body = SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'body',
            'min_value' => 54,
            'max_value' => 56,
            'unit' => 'cm',
        ]);

        $garment = SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'finished_garment',
            'min_value' => 60,
            'max_value' => 62,
            'unit' => 'cm',
        ]);

        $this->assertNotSame($body->id, $garment->id);
        $this->assertSame(2, SizeChartMeasurement::where('size_chart_entry_id', $entry->id)->count());
    }

    public function test_the_same_type_and_basis_is_rejected(): void
    {
        $entry = $this->entry($this->chart());

        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'body',
            'min_value' => 54,
            'max_value' => 56,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'body',
            'min_value' => 58,
            'max_value' => 60,
        ]);
    }

    public function test_entry_labels_are_unique_per_chart_and_fit_only(): void
    {
        $chart = $this->chart();
        $other = SizeChart::create(['name' => 'Boys 2026', 'version' => 1]);

        $this->entry($chart, '2-3 years', 'small');

        // Same age band, different fit, same chart: this is the whole point of tiers
        // and must be allowed so each fit can carry its own measurements.
        $this->entry($chart, '2-3 years', 'medium');

        // Same label in a different chart is fine.
        $this->entry($other, '2-3 years');

        // Only a genuine collision is rejected.
        $this->expectException(UniqueConstraintViolationException::class);
        $this->entry($chart, '2-3 years', 'small');
    }

    /**
     * MySQL treats NULLs as distinct inside a unique index, so two *untilered*
     * entries sharing a label are not caught by the database. This test pins that
     * known limitation down so it cannot drift silently; the admin editor and the
     * publish gate are responsible for rejecting the duplicate.
     */
    public function test_duplicate_untilered_entries_are_not_caught_by_the_database(): void
    {
        $chart = $this->chart();

        $this->entry($chart, '2-3 years');
        $this->entry($chart, '2-3 years');

        $this->assertSame(2, SizeChartEntry::where('size_chart_id', $chart->id)->count());
    }

    public function test_measurement_ranges_are_optional(): void
    {
        $entry = $this->entry($this->chart());

        $row = SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'full_dress_length',
            'basis' => 'finished_garment',
        ]);

        $this->assertNull($row->min_value);
        $this->assertNull($row->max_value);
        $this->assertSame('cm', $row->unit);
    }

    public function test_measurement_guides_are_basis_aware(): void
    {
        // Seeded guide copy is body measurement, so it becomes the body row.
        CustomOrderMeasurementGuide::create([
            'measurement_type' => 'chest',
            'basis' => 'body',
            'name' => 'Chest / Bust',
            'description' => 'Measure around the fullest part of the chest.',
        ]);

        CustomOrderMeasurementGuide::create([
            'measurement_type' => 'chest',
            'basis' => 'finished_garment',
            'name' => 'Chest (garment, flat)',
            'description' => 'Lay the garment flat and measure straight across.',
        ]);

        $body = CustomOrderMeasurementGuide::where('measurement_type', 'chest')
            ->where('basis', 'body')->first();
        $garment = CustomOrderMeasurementGuide::where('measurement_type', 'chest')
            ->where('basis', 'finished_garment')->first();

        $this->assertNotNull($body);
        $this->assertNotNull($garment);
        $this->assertNotSame($body->description, $garment->description);
    }
}
