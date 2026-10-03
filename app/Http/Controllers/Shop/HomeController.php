<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\CouponService;

class HomeController extends Controller
{
    public function __construct(private CouponService $coupons)
    {
    }

    public function index()
    {
        // Pool: the newest 30 active products. The row then picks a random 8,
        // one product per category per lap so different categories show first.
        $latest = Product::with(['primaryImage', 'variants.inventory', 'defaultVariant'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('selling_price', '>', 0)
                  ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->where('selling_price', '>', 0));
            })
            ->latest()
            ->take(30)
            ->get();

        $buckets  = $latest->shuffle()->groupBy('category_id');
        $featured = collect();

        while ($featured->count() < 8 && $buckets->contains(fn ($bucket) => $bucket->isNotEmpty())) {
            foreach ($buckets as $bucket) {
                if ($featured->count() >= 8) {
                    break 2;
                }

                if ($bucket->isNotEmpty()) {
                    $featured->push($bucket->shift());
                }
            }
        }

        $categories = Category::withCount('products')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->take(8)
            ->get();

        $coupons = $this->coupons->activeForDisplay();

        return view('shop.home', compact('featured', 'categories', 'coupons'));
    }
}