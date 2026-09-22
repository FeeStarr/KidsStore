<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends BaseController
{
    public function __construct(
        private CartService $cart,
    ) {}

    /**
     * GET /api/v1/cart
     */
    public function index(): JsonResponse
    {
        return $this->successResponse(new CartResource($this->cart));
    }

    /**
     * POST /api/v1/cart/items
     */
    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity'           => ['required', 'integer', 'min:1', 'max:100'],
            'age_group'          => ['nullable', 'string', 'max:100'],
            'selected_size'      => ['nullable', 'string', 'max:100'],
        ]);

        $variant = \App\Models\ProductVariant::with('inventory')->find($data['product_variant_id']);

        if (! $variant->is_active) {
            return $this->errorResponse('This product variant is not available.', 422);
        }

        $stock = $variant->inventory->quantity ?? 0;
        if ($stock < $data['quantity']) {
            return $this->errorResponse('Insufficient stock. Only ' . $stock . ' available.', 422);
        }

        $this->cart->add(
            $data['product_variant_id'],
            $data['quantity'],
            $data['age_group'] ?? null,
            $data['selected_size'] ?? null,
        );

        return $this->successResponse(new CartResource($this->cart), 'Item added to cart.');
    }

    /**
     * PATCH /api/v1/cart/items/{lineKey}
     */
    public function updateItem(Request $request, string $lineKey): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $this->cart->updateByLineKey($lineKey, $data['quantity']);

        return $this->successResponse(new CartResource($this->cart), 'Cart updated.');
    }

    /**
     * DELETE /api/v1/cart/items/{lineKey}
     */
    public function removeItem(string $lineKey): JsonResponse
    {
        $this->cart->removeByLineKey($lineKey);

        return $this->successResponse(new CartResource($this->cart), 'Item removed from cart.');
    }

    /**
     * DELETE /api/v1/cart
     */
    public function clear(): JsonResponse
    {
        $this->cart->clear();

        return $this->successResponse(new CartResource($this->cart), 'Cart cleared.');
    }
}
