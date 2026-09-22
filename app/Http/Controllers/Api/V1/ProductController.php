<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends BaseController
{
    /**
     * GET /api/v1/products
     */
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $query = Product::with(['primaryImage', 'brandRef', 'category', 'variants.inventory', 'defaultVariant'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where(function ($q) {
                $q->where('status', 'active')
                    ->orWhere(function ($q2) {
                        $q2->whereNull('status')->where('is_active', true);
                    });
            })
            ->where('selling_price', '>', 0);

        // Search
        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhereHas('brandRef', fn ($bq) => $bq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        // Category filter
        if ($categoryId = $request->input('category')) {
            $query->where(function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)
                    ->orWhereHas('category', fn ($cq) => $cq->where('parent_id', $categoryId));
            });
        }

        // Gender filter
        if ($gender = $request->input('gender')) {
            $query->where('gender', $gender);
        }

        // Age range filter
        if ($ageRange = $request->input('age_range')) {
            $query->whereHas('variants', fn ($vq) => $vq->where('age_range_id', $ageRange)->where('is_active', true));
        }

        // Sorting
        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'price_asc'  => $query->orderBy('selling_price'),
            'price_desc' => $query->orderByDesc('selling_price'),
            'name'       => $query->orderBy('name'),
            default      => $query->latest(),
        };

        $products = $query->paginate($request->input('per_page', 12));

        return ProductResource::collection($products);
    }

    /**
     * GET /api/v1/products/{product}
     */
    public function show(Product $product): JsonResponse
    {
        if ($product->status !== 'active' && ! ($product->status === null && $product->is_active)) {
            return $this->notFoundResponse('Product not found.');
        }

        $product->load([
            'images', 'brandRef', 'category', 'inventory',
            'reviews.customer',
            'variants.inventory', 'variants.image', 'variants.images',
            'variants.ageRange', 'variants.sizeRef', 'variants.colorRef',
        ]);

        return $this->successResponse(new ProductResource($product));
    }
}
