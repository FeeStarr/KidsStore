<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    public function __construct(
        private PaystackService $paystack,
    ) {}

    /**
     * POST /api/v1/payments/paystack/initialize
     */
    public function paystackInitialize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::find($data['order_id']);

        if ((int) $order->customer_id !== (int) $request->user()->id) {
            return $this->forbiddenResponse('You do not have access to this order.');
        }

        if (! $order->isPayNowEligible()) {
            return $this->errorResponse('This order is not eligible for payment.', 422);
        }

        $transaction = $this->paystack->initiate($order);

        return $this->successResponse([
            'reference'         => $transaction->reference,
            'access_code'       => $transaction->opay_payload['data']['access_code'] ?? null,
            'authorization_url' => $transaction->opay_payload['data']['authorization_url'] ?? null,
            'public_key'        => $this->paystack->publicKey(),
            'email'             => $request->user()->email,
            'amount'            => (float) $transaction->amount,
            'amount_kobo'       => (int) ($transaction->amount * 100),
            'expires_at'        => $transaction->expires_at?->toISOString(),
            'seconds_remaining' => $transaction->secondsRemaining(),
        ], 'Payment initialized.');
    }

    /**
     * GET /api/v1/payments/{reference}
     */
    public function query(Request $request, string $reference): JsonResponse
    {
        $transaction = PaymentTransaction::where('reference', $reference)->first();

        if (! $transaction) {
            return $this->notFoundResponse('Transaction not found.');
        }

        $order = $transaction->order;

        if ((int) $order->customer_id !== (int) $request->user()->id) {
            return $this->forbiddenResponse('You do not have access to this transaction.');
        }

        $transaction = $this->paystack->queryStatus($transaction);

        return $this->successResponse([
            'reference'         => $transaction->reference,
            'status'            => $transaction->status,
            'amount'            => (float) $transaction->amount,
            'expires_at'        => $transaction->expires_at?->toISOString(),
            'seconds_remaining' => $transaction->secondsRemaining(),
        ]);
    }
}
