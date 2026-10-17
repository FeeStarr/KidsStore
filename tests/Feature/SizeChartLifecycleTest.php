<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderMeasurementField;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use App\Models\User;
use RuntimeException;
use Tests\TestCase;

/**
 * Publication, locking and versioning.
 *
 * A chart must never reach a customer half-built, and once an order has been sized
 * against a chart version, that version's figures have to stay exactly as the customer
 * saw them. Both are enforced here.
 */
class SizeChartLifecycleTest extends TestCase
{
    private function chart(array $overrides = []): SizeChart
    {
        return SizeChart::create(array_merge([
            'name' => 'Girls Frocks',
            'default_basis' => 'finished_garment',
            'version' => 1,
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

    private function validMeasurements(): array
    {
        return [
            ['measurement_type' => 'chest', 'basis' => 'finished_garment', 'min_value' => 60, 'max_value' => 62],
        ];
    }

    private function order(): CustomOrder
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);

        return CustomOrder::create([
            'custom_order_number' => 'CO-SC-'.uniqid(),
            'user_id' => $user->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_QUOTED,
            'child_name' => 'Amara',
            'total_amount' => 15000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ]);
    }

    // ── publication gate ──────────────────────────────────────────────────

    public function test_an_empty_chart_cannot_be_published(): void
    {
        $chart = $this->chart();

        $this->assertFalse($chart->isPublishable());
        $this->assertContains('Add at least one size entry.', $chart->publicationBlockers());

        $this->expectException(RuntimeException::class);
        $chart->publish();
    }

    public function test_an_entry_without_measurements_blocks_publication(): void
    {
        $chart = $this->chart();
        SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => '2-3 years',
            'sort_order' => 1,
        ]);

        $chart->load('entries.measurements');

        $this->assertFalse($chart->isPublishable());
        $this->assertStringContainsString(
            'has no measurements',
            implode(' ', $chart->publicationBlockers())
        );
    }

    public function test_an_inverted_range_blocks_publication(): void
    {
        $chart = $this->chart();
        $this->entryWith($chart, [
            ['measurement_type' => 'chest', 'basis' => 'body', 'min_value' => 70, 'max_value' => 60],
        ]);

        $chart->load('entries.measurements');

        $this->assertFalse($chart->isPublishable());
        $this->assertStringContainsString(
            'minimum above its maximum',
            implode(' ', $chart->publicationBlockers())
        );
    }

    public function test_a_complete_chart_publishes(): void
    {
        $chart = $this->chart();
        $this->entryWith($chart, $this->validMeasurements());

        $chart->load('entries.measurements');
        $this->assertTrue($chart->isPublishable());

        $chart->publish();
        $chart->refresh();

        $this->assertTrue($chart->is_published);
        $this->assertNotNull($chart->published_at);
    }

    public function test_a_chart_with_only_one_basis_publishes(): void
    {
        // The tailor supplied finished-garment figures only. That is a valid chart.
        $chart = $this->chart();
        $entry = $this->entryWith($chart, $this->validMeasurements());
        $chart->load('entries.measurements');

        $this->assertTrue($chart->isPublishable());
        $this->assertSame(['finished_garment'], $entry->availableBases());
        $this->assertTrue($entry->hasBasis(SizeChart::BASIS_FINISHED_GARMENT));
        $this->assertFalse(
            $entry->hasBasis(SizeChart::BASIS_BODY),
            'A chart must not imply a body dataset it does not contain.'
        );
    }

    public function test_only_published_active_charts_are_offered(): void
    {
        $draft = $this->chart(['name' => 'Draft']);
        $published = $this->chart(['name' => 'Live']);
        $published->update(['is_published' => true, 'published_at' => now()]);

        $retired = $this->chart(['name' => 'Retired']);
        $retired->update(['is_published' => true, 'is_active' => false]);

        $names = SizeChart::published()->pluck('name')->all();

        $this->assertContains('Live', $names);
        $this->assertNotContains('Draft', $names);
        $this->assertNotContains('Retired', $names);
    }

    // ── locking ───────────────────────────────────────────────────────────

    public function test_locking_records_the_order_and_is_idempotent(): void
    {
        $chart = $this->chart();
        $order = $this->order();

        $chart->lockFor($order);
        $chart->refresh();

        $this->assertTrue($chart->is_locked);
        $this->assertSame($order->id, $chart->locked_by_order_id);

        // A second order must not steal the lock.
        $other = $this->order();
        $chart->lockFor($other);
        $chart->refresh();

        $this->assertSame($order->id, $chart->locked_by_order_id);
    }

    public function test_a_locked_chart_refuses_edits(): void
    {
        $chart = $this->chart();
        $entry = $this->entryWith($chart, $this->validMeasurements());
        $chart->lockFor($this->order());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked/i');

        $chart->assertNotLocked();
    }

    // ── versioning ────────────────────────────────────────────────────────

    public function test_new_version_copies_figures_and_starts_unpublished(): void
    {
        $chart = $this->chart();
        $this->entryWith($chart, $this->validMeasurements());
        $chart->load('entries.measurements');
        $chart->publish();

        $v2 = $chart->newVersion();

        $this->assertSame(2, $v2->version);
        $this->assertFalse($v2->is_published);
        $this->assertFalse($v2->is_locked);
        $this->assertNotSame($chart->id, $v2->id);

        $this->assertCount(1, $v2->entries);
        $this->assertSame('2-3 years', $v2->entries->first()->label);
        $this->assertCount(1, $v2->entries->first()->measurements);

        $copy = $v2->entries->first()->measurements->first();
        $this->assertSame('chest', $copy->measurement_type);
        $this->assertSame('finished_garment', $copy->basis);
        $this->assertEquals(60.0, $copy->min_value);
    }

    public function test_a_locked_chart_cannot_be_versioned(): void
    {
        $chart = $this->chart();
        $this->entryWith($chart, $this->validMeasurements());
        $chart->lockFor($this->order());

        $this->expectException(RuntimeException::class);
        $chart->newVersion();
    }

    public function test_editing_the_locked_original_leaves_the_new_version_independent(): void
    {
        $chart = $this->chart();
        $this->entryWith($chart, [
            ['measurement_type' => 'chest', 'basis' => 'finished_garment', 'min_value' => 60, 'max_value' => 62],
        ]);
        $chart->load('entries.measurements');

        $v2 = $chart->newVersion();
        $chart->lockFor($this->order());

        // Changing the new version must not disturb the locked original.
        $v2->entries->first()->measurements()->update(['max_value' => 70]);

        $this->assertEquals(62.0, (float) $chart->entries->first()->measurements->first()->max_value);
    }

    // ── presentation ──────────────────────────────────────────────────────

    public function test_measurement_values_render_without_inventing_figures(): void
    {
        $chart = $this->chart();
        $entry = $this->entryWith($chart, [
            ['measurement_type' => 'chest', 'basis' => 'body', 'min_value' => 54, 'max_value' => 56],
            ['measurement_type' => 'waist', 'basis' => 'body', 'min_value' => 50, 'max_value' => 50],
            ['measurement_type' => 'hip', 'basis' => 'body', 'min_value' => null, 'max_value' => null],
        ]);

        $rows = $entry->measurements()->get()->keyBy('measurement_type');

        $this->assertSame('54–56 cm', $rows['chest']->displayValue());
        $this->assertSame('50 cm', $rows['waist']->displayValue());
        $this->assertSame(
            '',
            $rows['hip']->displayValue(),
            'A missing figure must render blank rather than being invented.'
        );
    }

    public function test_measurement_labels_resolve_from_the_seeded_vocabulary(): void
    {
        CustomOrderMeasurementField::create([
            'name' => 'chest',
            'label' => 'Chest / Bust',
            'sort_order' => 1,
        ]);

        $chart = $this->chart();
        $entry = $this->entryWith($chart, $this->validMeasurements());

        $measurement = $entry->measurements->first();

        $this->assertSame('Chest / Bust', $measurement->label());
        $this->assertSame('Finished garment measurement', $measurement->basisLabel());
    }
}
