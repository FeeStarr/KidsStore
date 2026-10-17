<?php

namespace Tests\Feature;

use App\Models\CustomOrderMeasurementField;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use App\Models\User;
use Tests\TestCase;

/**
 * Discoverability of the size guide.
 *
 * A parent deciding whether to order needs to find the sizes without starting a
 * request first. Previously the guide was only in the footer and buried inside the
 * request wizard, so these cover the paths a customer is likely to take.
 */
class SizeGuideDiscoverabilityTest extends TestCase
{
    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
    }

    private function publishedChart(): SizeChart
    {
        $chart = SizeChart::create(['name' => 'Girls Frocks', 'default_basis' => 'body']);
        $entry = SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => '2-3 years',
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

    public function test_the_guide_is_linked_in_the_main_navigation(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'web')
            ->get(route('shop.products.index'))
            ->assertOk()
            ->assertSee(route('shop.size-guide'), false);
    }

    public function test_the_guide_is_reachable_from_the_public_custom_creations_gallery(): void
    {
        // No sign-in: a parent browsing designs should find sizes without an account.
        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee(route('shop.size-guide'), false);
    }

    public function test_the_guide_is_linked_from_the_request_form(): void
    {
        $customer = $this->customer();
        \App\Models\AgeRange::firstOrCreate(['name' => '2-3 years'], ['is_active' => true]);
        $this->publishedChart();

        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.create'))
            ->assertOk()
            ->assertSee(route('shop.size-guide'), false);
    }

    public function test_the_guide_page_is_publicly_reachable_without_signing_in(): void
    {
        $this->publishedChart();

        $this->get(route('shop.size-guide'))
            ->assertOk()
            ->assertSee('2-3 years');
    }
}