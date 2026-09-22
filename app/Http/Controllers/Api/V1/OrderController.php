<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends BaseController
{
    /**
     * GET /api/v1/orders
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()
            ->orders()
            ->with(['items.product', 'items.variant', 'pickupStation', 'deliveryLocation'])
            ->latest()
            ->paginate($request->input('per_page', 15));

        return OrderResource::collection($orders);
    }

    /**
     * GET /api/v1/orders/{order}
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        if ((int) $order->customer_id !== (int) $request->user()->id) {
            return $this->forbiddenResponse('You do not have access to this order.');
        }

        $order->load([
            'items.product', 'items.variant', 'items.deal', 'items.coupon',
            'pickupStation', 'deliveryLocation', 'paymentTransactions',
        ]);

        return $this->successResponse(new OrderResource($order));
    }
}
