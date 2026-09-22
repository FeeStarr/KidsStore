<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\CartService;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends BaseController
{
    public function __construct(
        private CartService $cart,
        private CouponService $coupons,
    ) {}

    /**
     * POST /api/v1/coupons/validate
     */
    public function validate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ]);

        try {
            $coupon = $this->cart->applyCoupon($data['code']);

            return $this->successResponse([
                'code'            => $coupon->code,
                'name'            => $coupon->name,
                'discount_type'   => $coupon->discount_type,
                'discount_value'  => (float) $coupon->discount_value,
                'discount_label'  => $coupon->discount_label,
                'discount_amount' => $this->cart->couponDiscount(),
            ], 'Coupon applied successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * DELETE /api/v1/coupons
     */
    public function remove(): JsonResponse
    {
        $this->cart->removeCoupon();

        return $this->successResponse(null, 'Coupon removed.');
    }
}
