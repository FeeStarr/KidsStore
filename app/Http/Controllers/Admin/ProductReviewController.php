<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function index(Request $request): View
    {
        $productId = (int) $request->input('product_id', 0);
        $rating    = (int) $request->input('rating', 0);

        $query = ProductReview::with(['product:id,name,sku', 'customer:id,name,email'])->latest();

        if ($productId > 0) {
            $query->where('product_id', $productId);
        }
        if ($rating >= 1 && $rating <= 5) {
            $query->where('rating', $rating);
        }

        return view('admin.product-reviews.index', [
            'reviews'         => $query->get(),
            'filterProductId' => $productId,
            'filterRating'    => $rating,
            'products'        => Product::whereHas('reviews')->orderBy('name')->get(['id', 'name', 'sku']),
            'totalReviews'    => ProductReview::count(),
            'averageRating'   => (float) (ProductReview::avg('rating') ?? 0),
            'reviewedProducts'=> ProductReview::distinct()->count('product_id'),
        ]);
    }
}
