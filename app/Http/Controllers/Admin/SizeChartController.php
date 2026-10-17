<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomOrderMeasurementField;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SizeChartController extends Controller
{
    public function index(): View
    {
        $charts = SizeChart::withCount('entries')->latest('id')->get();

        return view('admin.size-charts.index', compact('charts'));
    }

    public function create(): View
    {
        return view('admin.size-charts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateChart($request);

        $chart = SizeChart::create($data);

        return redirect()
            ->route('admin.size-charts.show', $chart)
            ->with('success', 'Chart created. Add sizes and measurements next.');
    }

    public function show(SizeChart $sizeChart): View
    {
        $sizeChart->load('entries.measurements');

        return view('admin.size-charts.show', [
            'chart' => $sizeChart,
            'blockers' => $sizeChart->publicationBlockers(),
            'measurementTypes' => CustomOrderMeasurementField::active()->get(),
            'bases' => SizeChart::BASES,
        ]);
    }

    public function edit(SizeChart $sizeChart): RedirectResponse
    {
        $sizeChart->assertNotLocked();

        return redirect()->route('admin.size-charts.show', $sizeChart);
    }

    public function update(Request $request, SizeChart $sizeChart): RedirectResponse
    {
        $sizeChart->assertNotLocked();

        $data = $this->validateChart($request);
        $sizeChart->update($data);

        return redirect()
            ->route('admin.size-charts.show', $sizeChart)
            ->with('success', 'Chart updated.');
    }

    public function publish(SizeChart $sizeChart): RedirectResponse
    {
        $sizeChart->assertNotLocked();
        $sizeChart->publish();

        return back()->with('success', 'Chart published. Customers can now select sizes from it.');
    }

    public function retire(SizeChart $sizeChart): RedirectResponse
    {
        $sizeChart->update(['is_active' => false, 'is_published' => false]);

        return back()->with('success', 'Chart retired. It is no longer offered to customers.');
    }

    /**
     * A locked version cannot be edited, so changes are made by copying it forward.
     */
    public function newVersion(SizeChart $sizeChart): RedirectResponse
    {
        $copy = $sizeChart->newVersion();

        return redirect()
            ->route('admin.size-charts.show', $copy)
            ->with('success', "Created version {$copy->version}. The original is unchanged.");
    }

    // ── entries ───────────────────────────────────────────────────────────

    public function storeEntry(Request $request, SizeChart $sizeChart): RedirectResponse
    {
        $sizeChart->assertNotLocked();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:64'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        $sizeChart->entries()->create([
            'label' => $data['label'],
            'code' => $data['code'] ?? null,
            'sort_order' => (int) $sizeChart->entries()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Size added.');
    }

    public function updateEntry(Request $request, SizeChart $sizeChart, SizeChartEntry $entry): RedirectResponse
    {
        $this->assertEntryBelongs($sizeChart, $entry);
        $sizeChart->assertNotLocked();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:64'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        $entry->update($data);

        return back()->with('success', 'Size updated.');
    }

    public function destroyEntry(SizeChart $sizeChart, SizeChartEntry $entry): RedirectResponse
    {
        $this->assertEntryBelongs($sizeChart, $entry);
        $sizeChart->assertNotLocked();

        $entry->delete();

        return back()->with('success', 'Size removed.');
    }

    // ── measurements ──────────────────────────────────────────────────────

    public function storeMeasurement(Request $request, SizeChart $sizeChart, SizeChartEntry $entry): RedirectResponse
    {
        $this->assertEntryBelongs($sizeChart, $entry);
        $sizeChart->assertNotLocked();

        $data = $request->validate($this->measurementRules($sizeChart, $entry));

        $entry->measurements()->create([
            'measurement_type' => $data['measurement_type'],
            'basis' => $data['basis'] ?? $sizeChart->default_basis,
            'min_value' => $data['min_value'] ?? null,
            'max_value' => $data['max_value'] ?? null,
            'unit' => $data['unit'] ?? 'cm',
        ]);

        return back()->with('success', 'Measurement added.');
    }

    public function updateMeasurement(
        Request $request,
        SizeChart $sizeChart,
        SizeChartEntry $entry,
        SizeChartMeasurement $measurement
    ): RedirectResponse {
        $this->assertEntryBelongs($sizeChart, $entry);
        $this->assertMeasurementBelongs($entry, $measurement);
        $sizeChart->assertNotLocked();

        $data = $request->validate($this->measurementRules($sizeChart, $entry, $measurement));

        $measurement->update([
            'measurement_type' => $data['measurement_type'],
            'basis' => $data['basis'] ?? $sizeChart->default_basis,
            'min_value' => $data['min_value'] ?? null,
            'max_value' => $data['max_value'] ?? null,
            'unit' => $data['unit'] ?? 'cm',
        ]);

        return back()->with('success', 'Measurement updated.');
    }

    public function destroyMeasurement(
        SizeChart $sizeChart,
        SizeChartEntry $entry,
        SizeChartMeasurement $measurement
    ): RedirectResponse {
        $this->assertEntryBelongs($sizeChart, $entry);
        $this->assertMeasurementBelongs($entry, $measurement);
        $sizeChart->assertNotLocked();

        $measurement->delete();

        return back()->with('success', 'Measurement removed.');
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function validateChart(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'default_basis' => ['nullable', Rule::in(SizeChart::BASES)],
        ]);
    }

    /**
     * The same measurement type may appear once per basis for a given size, so
     * uniqueness is on (measurement_type, basis) - not on measurement_type alone.
     * That is what lets a chart carry body and finished-garment figures side by side.
     */
    private function measurementRules(SizeChart $chart, SizeChartEntry $entry, ?SizeChartMeasurement $ignore = null): array
    {
        return [
            'measurement_type' => [
                'required', 'string', 'max:64',
                Rule::exists('custom_order_measurement_fields', 'name')->where('is_active', true),
            ],
            'basis' => ['nullable', Rule::in(SizeChart::BASES)],
            'min_value' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'max_value' => ['nullable', 'numeric', 'min:0', 'max:500', 'gte:min_value'],
            'unit' => ['nullable', 'string', 'max:8'],
        ];
    }

    private function assertEntryBelongs(SizeChart $chart, SizeChartEntry $entry): void
    {
        abort_unless((int) $entry->size_chart_id === (int) $chart->id, 404);
    }

    private function assertMeasurementBelongs(SizeChartEntry $entry, SizeChartMeasurement $measurement): void
    {
        abort_unless((int) $measurement->size_chart_entry_id === (int) $entry->id, 404);
    }
}
