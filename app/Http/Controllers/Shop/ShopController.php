<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $hideOutOfStock = config('shop.out_of_stock_visibility') === 'hide';

        $query = Product::with(['primaryImage', 'variants.inventory', 'defaultVariant', 'category'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('selling_price', '>', 0)
                  ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->where('selling_price', '>', 0));
            });

        if ($hideOutOfStock) {
            $query->whereHas('variants.inventory', fn ($q) => $q->where('quantity', '>', 0));
        }

        $activeCategory = null;
        if ($categoryId = $request->integer('category')) {
            $activeCategory = Category::with('descendants')->find($categoryId);
            $ids = $activeCategory ? $activeCategory->descendantIds() : [$categoryId];
            $query->whereIn('category_id', $ids);
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        $sort = $request->string('sort')->toString();
        match ($sort) {
            'price_asc'  => $query->orderBy('selling_price'),
            'price_desc' => $query->orderByDesc('selling_price'),
            'name'       => $query->orderBy('name'),
            default      => $query->latest(),
        };

        $products   = $query->paginate(12)->withQueryString();
        $categories = Category::with('children')->whereNull('parent_id')->orderBy('name')->get();

        return view('shop.products.index', compact('products', 'categories', 'activeCategory'));
    }

    public function show(Product $product)
    {
        $hideOutOfStock = config('shop.out_of_stock_visibility') === 'hide';

        abort_unless($product->is_active, 404);
        if ($hideOutOfStock && $product->stock_quantity <= 0) {
            abort(404);
        }

        $product->load([
            'images',
            'category',
            'inventory',
            'reviews.customer',
            'variants.inventory',
            'variants.image',
            'variants.images',
        ]);

        $related = Product::with(['primaryImage', 'variants.inventory', 'defaultVariant'])
            ->where('is_active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->when($hideOutOfStock, fn ($q) => $q->whereHas('variants.inventory', fn ($i) => $i->where('quantity', '>', 0)))
            ->take(4)
            ->get();

        return view('shop.products.show', compact('product', 'related'));
    }
}
