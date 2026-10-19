<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomOrderMeasurementField;
use App\Models\CustomOrderMeasurementGuide;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'guides' => $this->guidesByBasisAndType(),
            'guideUsage' => $this->guideUsage(),
        ]);
    }

    /**
     * How-to-measure copy, grouped so each basis can be labelled unambiguously.
     *
     * Guides are global: they carry no size_chart_id and are shared by every chart.
     * Body and finished-garment wording are genuinely different instructions, so they
     * are kept apart here rather than interleaved.
     */
    private function guidesByBasisAndType(): array
    {
        $guides = CustomOrderMeasurementGuide::active()
            ->get()
            ->groupBy('basis');

        return collect(SizeChart::BASES)
            ->mapWithKeys(fn ($basis) => [
                $basis => $guides->get($basis, collect())->keyBy('measurement_type'),
            ])
            ->all();
    }

    /**
     * Charts using each measurement type, derived from the database.
     *
     * This is what makes a guide edit's blast radius honest. It is never read from
     * submitted form data, so an admin cannot be tricked into believing a change is
     * narrower than it really is.
     */
    private function guideUsage(): array
    {
        $types = CustomOrderMeasurementGuide::query()->distinct()->pluck('measurement_type');

        $usage = [];

        foreach ($types as $type) {
            $usage[$type] = SizeChart::whereHas(
                'entries.measurements',
                fn ($q) => $q->where('measurement_type', $type)
            )->pluck('name')->all();
        }

        return $usage;
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

        $data = $request->validate($this->entryRules());
        $this->assertNoDuplicateUntilered($sizeChart, $data['label'], null, $data['fit'] ?? null);

        $sizeChart->entries()->create([
            'label' => $data['label'],
            // An empty selection arrives as null via ConvertEmptyStringsToNull, which
            // is what keeps "no fitting assigned" from silently becoming medium.
            'fit' => $data['fit'] ?? null,
            'code' => $data['code'] ?? null,
            'sort_order' => (int) $sizeChart->entries()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Size added.');
    }

    public function updateEntry(Request $request, SizeChart $sizeChart, SizeChartEntry $entry): RedirectResponse
    {
        $this->assertEntryBelongs($sizeChart, $entry);
        $sizeChart->assertNotLocked();

        $data = $request->validate($this->entryRules());
        $this->assertNoDuplicateUntilered($sizeChart, $data['label'], $entry, $data['fit'] ?? null);

        $entry->update([
            'label' => $data['label'],
            'fit' => $data['fit'] ?? null,
            'code' => $data['code'] ?? null,
        ]);

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

    // ── global measurement guides ───────────────────────────────────────────

    /**
     * Editing how-to-measure copy is global by nature: these rows have no
     * size_chart_id, so one edit is seen by customers on every chart.
     *
     * This is deliberately not blocked by $chart->is_locked. A lock protects a
     * chart's versioned measurements that an order was sized against; it says
     * nothing about whether an instruction is correct. Refusing to let the tailor
     * fix a wrong instruction because some unrelated chart was locked would make
     * the error harder to remove, not safer.
     */
    public function updateGuide(
        Request $request,
        SizeChart $sizeChart,
        CustomOrderMeasurementGuide $guide
    ): RedirectResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
            // accepted, not merely required: a crafted request could otherwise
            // submit any non-empty value and pass the confirmation.
            'confirm_global_change' => ['required', 'accepted'],
        ]);

        // Which charts this actually touches, straight from the database.
        $affected = SizeChart::whereHas(
            'entries.measurements',
            fn ($q) => $q->where('measurement_type', $guide->measurement_type)
        )->pluck('name')->all();

        $guide->update([
            'name' => $data['name'],
            'description' => $data['description'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $message = $affected === []
            ? 'Instruction updated. No chart currently uses this measurement.'
            : 'Instruction updated. It now also shows on: '.implode(', ', $affected).'.';

        return back()->with('success', $message);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function validateChart(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'default_basis' => ['nullable', Rule::in(SizeChart::BASES)],
        ]);
    }

    private function entryRules(): array
    {
        return [
            'label' => ['required', 'string', 'max:64'],
            'code' => ['nullable', 'string', 'max:32'],
            'fit' => ['nullable', Rule::in(SizeChart::FITS)],
        ];
    }

    /**
     * MySQL treats NULLs as distinct inside a unique index, so the database happily
     * accepts two untilered entries sharing a label even though
     * (size_chart_id, label, fit) claims to be unique. That case is enforced here
     * instead. Tiered collisions stay with the database, which handles them correctly.
     */
    private function assertNoDuplicateUntilered(
        SizeChart $chart,
        string $label,
        ?SizeChartEntry $ignore = null,
        ?string $fit = null
    ): void {
        // Only untilered rows need checking. Tiered collisions are caught by the
        // database, and an untilered row may legitimately share a label with a
        // tiered one - that is just partial coverage at an age band.
        if ($fit !== null) {
            return;
        }

        $query = SizeChartEntry::where('size_chart_id', $chart->id)
            ->where('label', $label)
            ->whereNull('fit');

        if ($ignore) {
            // Without this an update conflicts with the row being updated.
            $query->whereKeyNot($ignore->getKey());
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'fit' => 'This chart already has a size labelled "'.$label
                    .'" with no fitting assigned. Give it a fitting, or change the label.',
            ]);
        }
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
