<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'title'   => ['nullable', 'string', 'max:120'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $customerId = (int) Auth::id();

        // Only customers with a delivered order containing this product may review it
        $hasDelivered = Order::where('customer_id', $customerId)
            ->where('status', 'delivered')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        if (!$hasDelivered) {
            return back()->withErrors(['review' => 'You can only review products from delivered orders.']);
        }

        ProductReview::updateOrCreate(
            ['product_id' => $product->id, 'customer_id' => $customerId],
            [
                'rating'             => $data['rating'],
                'title'              => $data['title']   ?? null,
                'comment'            => $data['comment'] ?? null,
                'verified_purchase'  => true,
            ]
        );

        return back()->with('success', 'Thanks for your review!');
    }
}
