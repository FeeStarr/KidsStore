<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CustomOrderMeasurementGuide;
use App\Models\SizeChart;
use Illuminate\Contracts\View\View;

/**
 * Public size guide.
 *
 * Measurements are grouped by basis and only the bases a chart actually contains are
 * shown. A chart holding finished-garment figures alone must never imply that body
 * measurements were also supplied - conflating the two is how a parent ends up
 * choosing the wrong size.
 */
class SizeGuideController extends Controller
{
    public function show(): View
    {
        $charts = SizeChart::published()
            ->with(['entries' => fn ($q) => $q->orderBy('sort_order'), 'entries.measurements'])
            ->orderByDesc('is_published')
            ->get();

        // Which bases are genuinely present across the published charts.
        $bases = collect(SizeChart::BASES)
            ->filter(fn ($basis) => $charts->contains(
                fn ($chart) => $chart->entries->contains(
                    fn ($entry) => $entry->hasBasis($basis)
                )
            ))
            ->values();

        $guides = CustomOrderMeasurementGuide::active()
            ->whereIn('basis', $bases)
            ->get()
            ->keyBy(fn ($g) => $g->measurement_type.'|'.$g->basis);

        return view('shop.size-guide.show', [
            'charts' => $charts,
            'bases' => $bases,
            'guides' => $guides,
        ]);
    }
}
