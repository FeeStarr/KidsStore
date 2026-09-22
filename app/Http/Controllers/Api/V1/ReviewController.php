<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends BaseController
{
    /**
     * POST /api/v1/products/{product}/reviews
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'title'   => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        // Check if product exists and is active
        if ($product->status !== 'active' && ! ($product->status === null && $product->is_active)) {
            return $this->notFoundResponse('Product not found.');
        }

        // Check for existing review
        $existingReview = ProductReview::where('product_id', $product->id)
            ->where('customer_id', $request->user()->id)
            ->first();

        if ($existingReview) {
            return $this->errorResponse('You have already reviewed this product.', 409);
        }

        $review = ProductReview::create([
            'product_id'  => $product->id,
            'customer_id' => $request->user()->id,
            'rating'      => $data['rating'],
            'title'       => $data['title'] ?? null,
            'comment'     => $data['comment'] ?? null,
        ]);

        return $this->createdResponse([
            'id'       => $review->id,
            'rating'   => $review->rating,
            'title'    => $review->title,
            'comment'  => $review->comment,
        ], 'Review submitted successfully.');
    }
}
