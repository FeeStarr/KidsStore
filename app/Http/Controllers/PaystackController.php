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
use Illuminate\Support\Str;

class PaystackController extends Controller
{
    public function __construct(private PaystackService $paystack)
    {
    }

    // ── Customer: initiate payment ────────────────────────────────────────────

    /**
     * POST /account/orders/{order}/pay
     * Initializes a standard Paystack transaction and returns the data the
     * front-end needs to open the Paystack Inline popup.
     */
    public function initiate(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        abort_unless((int) $order->customer_id === (int) Auth::id(), 403);
        abort_if($order->payment_status === 'paid', 400, 'This order is already paid.');
        abort_if($order->status === 'cancelled', 400, 'Cannot pay a cancelled order.');

        try {
            $transaction = $this->paystack->initiate($order);
        } catch (\RuntimeException $e) {
            Log::warning('Paystack initiate failed', ['order' => $order->reference, 'error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Unable to initiate payment. Please try again.'], 422);
            }
            return back()->with('error', 'Unable to initiate payment. Please try again.');
        }

        $payload = $transaction->opay_payload['data'] ?? [];

        $response = [
            'success'           => true,
            'reference'         => $transaction->reference,
            'access_code'       => $payload['access_code'] ?? null,
            'authorization_url' => $payload['authorization_url'] ?? null,
            'public_key'        => $this->paystack->publicKey(),
            'email'             => $order->customer?->email ?? '',
            'amount'            => (float) $transaction->amount,
            'amount_kobo'       => (int) round((float) $transaction->amount * 100),
            'expires_at'        => $transaction->expires_at?->toISOString(),
            'seconds_remaining' => $transaction->secondsRemaining(),
        ];

        session()->flash('paystack_transaction', $transaction->id);

        return response()->json($response);
    }

    // ── Customer: Paystack redirect callback ─────────────────────────────────

    /**
     * GET /account/orders/{order}/pay/callback
     * Paystack redirects the customer here after payment (popup fallback).
     * Verifies the transaction and bounces back to the order page.
     */
    public function callback(Request $request, Order $order): RedirectResponse
    {
        abort_unless((int) $order->customer_id === (int) Auth::id(), 403);

        $reference = $request->query('reference') ?? $request->query('trxref');

        if ($reference) {
            $transaction = $order->paymentTransactions()
                ->where('reference', $reference)
                ->orWhere('opay_order_no', $reference)
                ->latest()
                ->first();

            if ($transaction) {
                $transaction = $this->paystack->queryStatus($transaction);
                $order->refresh();

                if ($order->payment_status === 'paid') {
                    return redirect()->route('shop.account.orders.show', $order)
                        ->with('success', 'Payment confirmed. Thank you!');
                }

                if ($order->payment_status === 'under_review') {
                    return redirect()->route('shop.account.orders.show', $order)
                        ->with('info', 'Payment received and is under review.');
                }
            }
        }

        return redirect()->route('shop.account.orders.show', $order)
            ->with('error', 'We could not confirm your payment yet. Please check again shortly.');
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

    // ── Guest: initiate payment ───────────────────────────────────────────────

    /**
     * POST /order-track/{token}/pay
     * Guest-accessible payment initiation using lookup token.
     */
    public function guestInitiate(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $order = Order::where('lookup_token', $token)->firstOrFail();
        abort_if($order->payment_status === 'paid', 400, 'This order is already paid.');
        abort_if($order->status === 'cancelled', 400, 'Cannot pay a cancelled order.');

        try {
            $transaction = $this->paystack->initiate($order);
        } catch (\RuntimeException $e) {
            Log::warning('Paystack guest initiate failed', ['order' => $order->reference, 'error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Unable to initiate payment. Please try again.'], 422);
            }
            return back()->with('error', 'Unable to initiate payment. Please try again.');
        }

        $payload = $transaction->opay_payload['data'] ?? [];

        $response = [
            'success'           => true,
            'reference'         => $transaction->reference,
            'access_code'       => $payload['access_code'] ?? null,
            'authorization_url' => $payload['authorization_url'] ?? null,
            'public_key'        => $this->paystack->publicKey(),
            'email'             => $order->guest_email ?? $order->customer?->email ?? '',
            'amount'            => (float) $transaction->amount,
            'amount_kobo'       => (int) round((float) $transaction->amount * 100),
            'expires_at'        => $transaction->expires_at?->toISOString(),
            'seconds_remaining' => $transaction->secondsRemaining(),
        ];

        session()->flash('paystack_transaction', $transaction->id);

        return response()->json($response);
    }

    // ── Guest: Paystack redirect callback ─────────────────────────────────────

    /**
     * GET /order-track/{token}/pay/callback
     */
    public function guestCallback(Request $request, string $token): RedirectResponse
    {
        $order = Order::where('lookup_token', $token)->firstOrFail();
        $reference = $request->query('reference') ?? $request->query('trxref');

        if ($reference) {
            $transaction = $order->paymentTransactions()
                ->where('reference', $reference)
                ->orWhere('opay_order_no', $reference)
                ->latest()
                ->first();

            if ($transaction) {
                $transaction = $this->paystack->queryStatus($transaction);
                $order->refresh();

                if ($order->payment_status === 'paid') {
                    return redirect()->route('shop.order.track', $token)
                        ->with('success', 'Payment confirmed. Thank you!');
                }

                if ($order->payment_status === 'under_review') {
                    return redirect()->route('shop.order.track', $token)
                        ->with('info', 'Payment received and is under review.');
                }
            }
        }

        return redirect()->route('shop.order.track', $token)
            ->with('error', 'We could not confirm your payment yet. Please check again shortly.');
    }

    // ── Guest: query status ───────────────────────────────────────────────────

    /**
     * POST /order-track/{token}/pay/query
     */
    public function guestQuery(Request $request, string $token): JsonResponse
    {
        $order = Order::where('lookup_token', $token)->firstOrFail();

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
