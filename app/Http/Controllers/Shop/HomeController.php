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
        $featured = Product::with(['primaryImage', 'variants.inventory', 'defaultVariant'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('selling_price', '>', 0)
                  ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->where('selling_price', '>', 0));
            })
            ->latest()
            ->take(8)
            ->get();

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