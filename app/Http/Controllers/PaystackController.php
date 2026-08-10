<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaystackController extends Controller
{
    public function __construct(private PaystackService $paystack)
    {
    }

    // ── Customer: initiate payment ────────────────────────────────────────────

    /**
     * POST /orders/{order}/pay
     * Creates a dedicated virtual account and returns it to the customer.
     */
    public function initiate(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        abort_unless((int) $order->customer_id === (int) Auth::id(), 403);
        abort_if($order->payment_status === 'paid', 400, 'This order is already paid.');
        abort_if($order->status === 'cancelled', 400, 'Cannot pay a cancelled order.');

        try {
            $transaction = $this->paystack->initiate($order);
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success'                => true,
                'reference'              => $transaction->reference,
                'virtual_account_number' => $transaction->virtual_account_number,
                'virtual_bank_name'      => $transaction->virtual_bank_name,
                'amount'                 => (float) $transaction->amount,
                'expires_at'             => $transaction->expires_at?->toISOString(),
                'seconds_remaining'      => $transaction->secondsRemaining(),
            ]);
        }

        return back()->with('paystack_transaction', $transaction->id);
    }

    // ── Customer: query status ────────────────────────────────────────────────

    /**
     * POST /orders/{order}/pay/query
     * Checks whether payment has been received.
     */
    public function query(Request $request, Order $order): JsonResponse
    {
        abort_unless((int) $order->customer_id === (int) Auth::id(), 403);

        $transaction = $order->paymentTransactions()
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'status'  => $order->payment_status,
                'message' => 'No pending payment found.',
            ]);
        }

        // Throttle: minimum 15s between queries
        if ($transaction->last_queried_at &&
            now()->diffInSeconds($transaction->last_queried_at) < 15) {
            return response()->json([
                'success'          => true,
                'status'           => $transaction->status,
                'payment_status'   => $order->fresh()->payment_status,
                'seconds_remaining'=> $transaction->secondsRemaining(),
                'throttled'        => true,
            ]);
        }

        $transaction = $this->paystack->queryStatus($transaction);
        $order->refresh();

        return response()->json([
            'success'          => true,
            'status'           => $transaction->status,
            'payment_status'   => $order->payment_status,
            'seconds_remaining'=> $transaction->secondsRemaining(),
            'paid'             => $transaction->isSuccess(),
        ]);
    }

    // ── Paystack Webhook (unauthenticated, CSRF-exempt) ──────────────────────

    /**
     * POST /paystack/webhook
     * Paystack sends payment status updates here.
     * Must return HTTP 200 to acknowledge.
     */
    public function webhook(Request $request): Response
    {
        $rawBody  = $request->getContent();
        $body      = json_decode($rawBody, true) ?? [];
        $signature = $request->header('X-Paystack-Signature', '');

        try {
            $this->paystack->handleWebhook($body, $signature, $rawBody);
        } catch (\Throwable $e) {
            Log::error('Paystack webhook exception', ['error' => $e->getMessage()]);
        }

        // Always return 200 — Paystack will retry on non-2xx
        return response('OK', 200);
    }
}
