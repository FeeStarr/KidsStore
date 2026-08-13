<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Deal;

class DealController extends Controller
{
    public function index()
    {
        $deals = Deal::with(['products.primaryImage', 'products.defaultVariant', 'products.category'])
            ->withCount('products')
            ->live()
            ->orderByDesc('is_featured')
            ->orderByDesc('starts_at')
            ->get();

        // Upcoming deals (scheduled), shown separately where appropriate.
        $upcoming = Deal::withCount('products')
            ->started()
            ->where('starts_at', '>', now())
            ->whereNotIn('status', [Deal::STATUS_DRAFT, Deal::STATUS_CANCELLED])
            ->orderBy('starts_at')
            ->get();

        return view('shop.deals.index', compact('deals', 'upcoming'));
    }

    public function show(Deal $deal)
    {
        abort_unless($deal->is_live || $deal->computedStatus() === Deal::STATUS_SCHEDULED, 404);

        $deal->load([
            'products.primaryImage',
            'products.defaultVariant',
            'products.variants.inventory',
            'products.category',
        ]);

        return view('shop.deals.show', compact('deal'));
    }
}
