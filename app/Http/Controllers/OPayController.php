<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\OPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OPayController extends Controller
{
    public function __construct(private OPayService $opay)
    {
    }

    // ── Customer: initiate payment ────────────────────────────────────────────

    /**
     * POST /orders/{order}/pay
     * Customer clicks "Pay Now" - creates a virtual account on OPay and returns it.
     */
    public function initiate(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        // Only the order owner can pay
        abort_unless((int) $order->customer_id === (int) Auth::id(), 403);
        abort_if($order->payment_status === 'paid', 400, 'This order is already paid.');
        abort_if($order->status === 'cancelled', 400, 'Cannot pay a cancelled order.');

        try {
            $transaction = $this->opay->initiate($order);
        } catch (\RuntimeException $e) {
            Log::warning('OPay initiate failed', ['order' => $order->reference, 'error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Unable to initiate payment. Please try again.'], 422);
            }
            return back()->with('error', 'Unable to initiate payment. Please try again.');
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

        return back()->with('opay_transaction', $transaction->id);
    }

    // ── Customer: query status ────────────────────────────────────────────────

    /**
     * POST /orders/{order}/pay/query
     * Customer (or JS polling) checks whether payment has been received.
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

        // Throttle: don't hammer OPay - minimum 15 s between queries
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

        $transaction = $this->opay->queryStatus($transaction);
        $order->refresh();

        return response()->json([
            'success'          => true,
            'status'           => $transaction->status,
            'payment_status'   => $order->payment_status,
            'seconds_remaining'=> $transaction->secondsRemaining(),
            'paid'             => $transaction->isSuccess(),
        ]);
    }

    // ── OPay Webhook (unauthenticated, CSRF-exempt) ───────────────────────────

    /**
     * POST /opay/webhook
     * OPay sends payment status updates here.
     * Must return HTTP 200 to acknowledge.
     */
    public function webhook(Request $request): Response
    {
        $body      = $request->all();
        $signature = $body['sha512'] ?? '';

        try {
            $this->opay->handleWebhook($body, $signature);
        } catch (\Throwable $e) {
            Log::error('OPay webhook exception', ['error' => $e->getMessage()]);
        }

        // Always return 200 - OPay will retry on non-2xx for 72 hours
        return response('OK', 200);
    }
}
