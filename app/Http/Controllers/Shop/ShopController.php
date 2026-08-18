<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\AgeRange;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $hideOutOfStock = config('shop.out_of_stock_visibility') === 'hide';

        $query = Product::with(['primaryImage', 'brandRef', 'variants.inventory', 'defaultVariant', 'category'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where(function ($q) {
                $q->where('status', 'active')
                  ->orWhere(function ($legacy) {
                      $legacy->whereNull('status')->where('is_active', true);
                  });
            })
            ->where(function ($q) {
                $q->where('selling_price', '>', 0)
                  ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->where('selling_price', '>', 0));
            });

        if ($hideOutOfStock) {
            $query->whereHas('variants.inventory', fn ($q) => $q->where('quantity', '>', 0));
        }

        $activeCategory = null;
        if ($categoryId = $request->integer('category')) {
            $activeCategory = Category::with(['children' => fn ($q) => $q->where('is_active', true)])->find($categoryId);
            if (! $activeCategory || ! $activeCategory->is_active) {
                $activeCategory = null;
            } else {
                $ids = array_merge([$activeCategory->id], $activeCategory->children->pluck('id')->toArray());
                // If this is a subcategory, also include its parent so products
                // assigned to the parent still appear when filtering by child.
                if ($activeCategory->parent_id) {
                    $ids[] = $activeCategory->parent_id;
                }
                $query->whereIn('category_id', $ids);
            }
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhereHas('brandRef', fn ($b) => $b->where('name', 'like', "%{$search}%"));
            });
        }

        // Filter by age range (variant-level age_range_id).
        if ($ageRangeId = $request->integer('age_range')) {
            $query->whereHas('variants', fn ($v) => $v->where('is_active', true)->where('age_range_id', $ageRangeId));
        }

        $sort = $request->string('sort')->toString();
        match ($sort) {
            'price_asc'  => $query->orderBy('selling_price'),
            'price_desc' => $query->orderByDesc('selling_price'),
            'name'       => $query->orderBy('name'),
            default      => $query->latest(),
        };

        $products   = $query->paginate(12)->withQueryString();
        $categories = Category::with(['children' => fn ($q) => $q->where('is_active', true)])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $ageRanges = AgeRange::where('is_active', true)->orderBy('name')->get();

        return view('shop.products.index', compact('products', 'categories', 'activeCategory', 'ageRanges'));
    }

    public function show(Product $product)
    {
        $hideOutOfStock = config('shop.out_of_stock_visibility') === 'hide';

        $isActive = $product->status ? $product->status === 'active' : (bool) $product->is_active;
        abort_unless($isActive, 404);
        if ($hideOutOfStock && $product->stock_quantity <= 0) {
            abort(404);
        }

        $product->load([
            'images',
            'brandRef',
            'category',
            'inventory',
            'reviews.customer',
            'variants.inventory',
            'variants.image',
            'variants.images',
            'variants.ageRange',
            'variants.sizeRef',
            'variants.colorRef',
        ]);

        $related = Product::with(['primaryImage', 'brandRef', 'variants.inventory', 'defaultVariant'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where(function ($q) {
                $q->where('status', 'active')
                  ->orWhere(function ($legacy) {
                      $legacy->whereNull('status')->where('is_active', true);
                  });
            })
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->when($hideOutOfStock, fn ($q) => $q->whereHas('variants.inventory', fn ($i) => $i->where('quantity', '>', 0)))
            ->take(4)
            ->get();

        return view('shop.products.show', compact('product', 'related'));
    }
}
