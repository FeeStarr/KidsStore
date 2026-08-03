<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Paystack — Dedicated Virtual Account integration.
 *
 * Docs: https://paystack.com/docs/api/#dedicated-virtual-account
 *
 * Configure via config/paystack.php + .env:
 *   PAYSTACK_SECRET_KEY=sk_test_xxx
 *   PAYSTACK_PUBLIC_KEY=pk_test_xxx
 *   PAYSTACK_WEBHOOK_SECRET=whsec_xxx
 */
class PaystackService
{
    private string $secretKey;
    private string $publicKey;
    private string $webhookSecret;
    private string $baseUrl;
    private int    $expireMinutes;

    public function __construct()
    {
        $this->secretKey     = (string) config('paystack.secret_key');
        $this->publicKey     = (string) config('paystack.public_key');
        $this->webhookSecret = (string) config('paystack.webhook_secret');
        $this->baseUrl       = (string) config('paystack.base_url');
        $this->expireMinutes = (int) config('paystack.expire_minutes', 45);
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Create a dedicated virtual account for the customer and return a PaymentTransaction.
     *
     * @throws \RuntimeException if Paystack returns an error
     */
    public function initiate(Order $order): PaymentTransaction
    {
        // Expire any previous pending transactions for this order
        $order->paymentTransactions()
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $reference = $this->generateReference($order);

        // ── Stub mode: no credentials configured ─────────────────────────────
        if (empty($this->secretKey)) {
            return PaymentTransaction::create([
                'order_id'               => $order->id,
                'reference'              => $reference,
                'opay_order_no'          => null,
                'virtual_account_number' => '0000000000',
                'virtual_bank_name'      => 'Paystack (Test — no credentials)',
                'amount'                 => (float) $order->grand_total,
                'status'                 => 'pending',
                'expires_at'             => now()->addMinutes($this->expireMinutes),
                'opay_payload'           => ['note' => 'Stub — PAYSTACK_SECRET_KEY not set'],
            ]);
        }
        // ─────────────────────────────────────────────────────────────────────

        $amountKobo = (int) round((float) $order->grand_total * 100);
        $email = $order->customer?->email ?? 'customer@kidsstore.com';

        // Step 1: Create (or get existing) customer
        $customer = $this->getOrCreateCustomer($email, $order->customer?->name ?? 'Customer');

        // Step 2: Assign a dedicated virtual account
        $response = $this->post('/dedicated_account', [
            'customer'    => $customer['id'],
            'preferred_bank' => 'wema-bank', // Paystack's primary virtual account bank
            'currency'    => config('paystack.currency', 'NGN'),
        ]);

        if (! ($response['status'] ?? false)) {
            $msg = $response['message'] ?? 'Unknown Paystack error';
            Log::error('Paystack initiate failed', ['order' => $order->reference, 'response' => $response]);
            throw new \RuntimeException("Payment initiation failed: {$msg}");
        }

        $data = $response['data'];

        $transaction = PaymentTransaction::create([
            'order_id'               => $order->id,
            'reference'              => $reference,
            'opay_order_no'          => $data['id'] ?? null,
            'virtual_account_number' => $data['account_number'] ?? null,
            'virtual_bank_name'      => ($data['bank']['name'] ?? 'Wema Bank') . ' (' . ($data['bank']['slug'] ?? 'wema-bank') . ')',
            'amount'                 => (float) $order->grand_total,
            'status'                 => 'pending',
            'expires_at'             => now()->addMinutes($this->expireMinutes),
            'opay_payload'           => $response,
        ]);

        return $transaction;
    }

    /**
     * Query current status of a transaction from Paystack.
     */
    public function queryStatus(PaymentTransaction $transaction): PaymentTransaction
    {
        $transaction->update(['last_queried_at' => now()]);

        // Stub mode — simulate success
        if (empty($this->secretKey)) {
            $this->markTransactionSuccess($transaction, ['note' => 'Stub success — no credentials configured']);
            return $transaction->refresh();
        }

        // Verify transaction via Paystack API
        $response = $this->get("/transaction/verify/{$transaction->reference}");

        if (! ($response['status'] ?? false)) {
            return $transaction->refresh();
        }

        $data = $response['data'];
        $paystackStatus = strtolower($data['status'] ?? '');

        if ($paystackStatus === 'success') {
            $this->markTransactionSuccess($transaction, $response);
        } elseif (in_array($paystackStatus, ['abandoned', 'failed', 'reversed'], true)) {
            $transaction->update([
                'status'       => 'failed',
                'opay_payload' => $response,
            ]);
        }

        return $transaction->refresh();
    }

    /**
     * Handle an incoming webhook from Paystack.
     * Verifies the signature, then updates the transaction + order.
     */
    public function handleWebhook(array $payload, string $signature): bool
    {
        if (! $this->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Paystack webhook signature mismatch', ['payload' => $payload]);
            return false;
        }

        $event = $payload['event'] ?? '';
        $data  = $payload['data'] ?? [];

        // We care about charge.success events
        if ($event !== 'charge.success') {
            return false;
        }

        $reference = $data['reference'] ?? null;
        if (! $reference) return false;

        $transaction = PaymentTransaction::where('reference', $reference)->first();
        if (! $transaction) {
            Log::warning('Paystack webhook: transaction not found', ['reference' => $reference]);
            return false;
        }

        $paystackStatus = strtolower($data['status'] ?? '');

        if ($paystackStatus === 'success') {
            $this->markTransactionSuccess($transaction, $payload);
        } elseif (in_array($paystackStatus, ['abandoned', 'failed', 'reversed'], true)) {
            $transaction->update([
                'status'       => 'failed',
                'opay_payload' => $payload,
            ]);
        }

        return true;
    }

    /**
     * Initiate a refund via Paystack.
     * Docs: https://paystack.com/docs/api/#refund
     */
    public function refund(string $transactionReference, float $amount, string $refundRef): array
    {
        $amountKobo = (int) round($amount * 100);

        return $this->post('/refund', [
            'transaction' => $transactionReference,
            'amount'      => $amountKobo,
            'currency'    => config('paystack.currency', 'NGN'),
            'note'        => 'Customer refund request',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getOrCreateCustomer(string $email, string $name): array
    {
        // Try to find existing customer by email
        $response = $this->get("/customer?email=" . urlencode($email));

        if (($response['status'] ?? false) && ! empty($response['data'])) {
            $customers = is_array($response['data']) ? $response['data'] : [$response['data']];
            if (! empty($customers)) {
                return $customers[0];
            }
        }

        // Create new customer
        $response = $this->post('/customer', [
            'email' => $email,
            'first_name' => $name,
        ]);

        if (! ($response['status'] ?? false)) {
            throw new \RuntimeException('Failed to create Paystack customer: ' . ($response['message'] ?? 'Unknown error'));
        }

        return $response['data'];
    }

    private function markTransactionSuccess(PaymentTransaction $transaction, array $rawPayload): void
    {
        $transaction->update([
            'status'       => 'success',
            'opay_payload' => $rawPayload,
        ]);

        // Update the parent order
        $order = $transaction->order;
        if ($order && $order->payment_status !== 'paid') {
            $paid = (float) $order->amount_paid + (float) $transaction->amount;
            $order->update([
                'amount_paid'    => $paid,
                'payment_status' => $paid >= (float) $order->grand_total ? 'paid' : 'partial',
            ]);
        }
    }

    private function generateReference(Order $order): string
    {
        return 'KS-' . $order->reference . '-' . strtoupper(Str::random(6));
    }

    /**
     * Verify a Paystack webhook signature.
     * Paystack uses HMAC SHA-512 with your secret key.
     */
    private function verifyWebhookSignature(array $payload, string $receivedSignature): bool
    {
        if (empty($this->webhookSecret)) {
            Log::error('Paystack webhook rejected: PAYSTACK_WEBHOOK_SECRET is not configured.');
            return false;
        }

        $json     = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expected = hash_hmac('sha512', $json, $this->webhookSecret);

        return hash_equals($expected, $receivedSignature);
    }

    /**
     * GET request to Paystack API.
     */
    private function get(string $endpoint): array
    {
        $http = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept'        => 'application/json',
            ]);

        if (config('app.env') !== 'production') {
            $http = $http->withoutVerifying();
        }

        $response = $http->get($this->baseUrl . $endpoint);

        return $response->json() ?? [];
    }

    /**
     * POST to Paystack API.
     */
    private function post(string $endpoint, array $data): array
    {
        $http = Http::timeout(30)
            ->withHeaders([
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept'        => 'application/json',
            ]);

        if (config('app.env') !== 'production') {
            $http = $http->withoutVerifying();
        }

        $response = $http->post($this->baseUrl . $endpoint, $data);

        return $response->json() ?? [];
    }
}
