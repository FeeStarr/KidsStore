<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OPay Checkout - Bank Transfer integration.
 *
 * Docs: https://documentation.opaycheckout.com/bank-transfer
 *
 * Configure via config/opay.php + .env:
 *   OPAY_MERCHANT_ID=...
 *   OPAY_SECRET_KEY=...
 *   OPAY_ENV=staging|production
 */
class OPayService
{
    private string $merchantId;
    private string $secretKey;
    private string $baseUrl;
    private int    $expireMinutes;

    public function __construct()
    {
        $this->merchantId    = (string) config('opay.merchant_id');
        $this->secretKey     = (string) config('opay.secret_key');
        $env                 = config('opay.env', 'staging');
        $this->baseUrl       = config("opay.base_url.{$env}");
        $this->expireMinutes = (int) config('opay.expire_minutes', 40);
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Create a BankTransfer order on OPay and return a PaymentTransaction.
     * The transaction contains the virtual account number to show the customer.
     *
     * @throws \RuntimeException if OPay returns an error
     */
    public function initiate(Order $order): PaymentTransaction
    {
        // Expire any previous pending transactions for this order
        $order->paymentTransactions()
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $reference = $this->generateReference($order);

        // ── Stub mode: no credentials configured ─────────────────────────────
        if (empty($this->merchantId) || empty($this->secretKey)) {
            return PaymentTransaction::create([
                'order_id'               => $order->id,
                'reference'              => $reference,
                'opay_order_no'          => null,
                'virtual_account_number' => '0000000000',
                'virtual_bank_name'      => 'OPay (Test - no credentials)',
                'amount'                 => (float) $order->grand_total,
                'status'                 => 'pending',
                'expires_at'             => now()->addMinutes($this->expireMinutes),
                'opay_payload'           => ['note' => 'Stub - OPAY_MERCHANT_ID / OPAY_SECRET_KEY not set'],
            ]);
        }
        // ─────────────────────────────────────────────────────────────────────
        $amountKobo = (int) round((float) $order->grand_total * 100);

        $payload = [
            'amount'      => ['currency' => 'NGN', 'total' => $amountKobo],
            'callbackUrl' => route('opay.webhook'),
            'country'     => 'NG',
            'customerName'=> $order->customer?->name ?? 'Customer',
            'payMethod'   => 'BankTransfer',
            'product'     => [
                'name'        => 'KidsStore Order ' . $order->reference,
                'description' => 'Order ' . $order->reference,
            ],
            'reference'   => $reference,
            'expireAt'    => $this->expireMinutes,
            'userInfo'    => [
                'userId'     => (string) ($order->customer_id ?? 'guest'),
                'userName'   => $order->customer?->name ?? '',
                'userMobile' => $order->customer?->phone ?? '',
                'userEmail'  => $order->customer?->email ?? '',
            ],
        ];

        $response = $this->post(config('opay.endpoints.create'), $payload);

        if (($response['code'] ?? '') !== '00000') {
            $msg = $response['message'] ?? 'Unknown OPay error';
            Log::error('OPay initiate failed', ['order' => $order->reference, 'response' => $response]);
            throw new \RuntimeException("Payment initiation failed: {$msg}");
        }

        $data       = $response['data'];
        $nextAction = $data['nextAction'] ?? [];

        $transaction = PaymentTransaction::create([
            'order_id'               => $order->id,
            'reference'              => $reference,
            'opay_order_no'          => $data['orderNo'] ?? null,
            'virtual_account_number' => $nextAction['transferAccountNumber'] ?? null,
            'virtual_bank_name'      => $nextAction['transferBankName'] ?? null,
            'amount'                 => (float) $order->grand_total,
            'status'                 => 'pending',
            'expires_at'             => isset($nextAction['expiredTimestamp'])
                ? \Carbon\Carbon::createFromTimestamp($nextAction['expiredTimestamp'])
                : now()->addMinutes($this->expireMinutes),
            'opay_payload'           => $response,
        ]);

        return $transaction;
    }

    /**
     * Query current status of a transaction directly from OPay.
     * Updates the local record and order payment_status if paid.
     */
    public function queryStatus(PaymentTransaction $transaction): PaymentTransaction
    {
        $transaction->update(['last_queried_at' => now()]);

        // Stub mode - nothing to query, simulate success so testing can proceed
        if (empty($this->merchantId) || empty($this->secretKey)) {
            $this->markTransactionSuccess($transaction, ['note' => 'Stub success - no credentials configured']);
            return $transaction->refresh();
        }

        $response = $this->post(config('opay.endpoints.query'), [
            'orderNo'   => $transaction->opay_order_no,
            'reference' => $transaction->reference,
            'country'   => 'NG',
        ]);

        $opayStatus = strtoupper($response['data']['status'] ?? '');

        if ($opayStatus === 'SUCCESS') {
            $this->markTransactionSuccess($transaction, $response);
        } elseif (in_array($opayStatus, ['FAIL', 'CLOSE'], true)) {
            $transaction->update([
                'status'        => 'failed',
                'opay_payload'  => $response,
            ]);
        }

        return $transaction->refresh();
    }

    /**
     * Handle an incoming webhook from OPay.
     * Verifies the signature, then updates the transaction + order.
     *
     * @param  array  $payload  The decoded JSON body
     * @param  string $signature The sha512 field from the payload
     * @return bool  Whether the webhook was valid and handled
     */
    public function handleWebhook(array $payload, string $signature): bool
    {
        if (! $this->verifyWebhookSignature($payload['payload'] ?? [], $signature)) {
            Log::warning('OPay webhook signature mismatch', ['payload' => $payload]);
            return false;
        }

        $reference  = $payload['payload']['reference'] ?? null;
        $opayStatus = strtoupper($payload['payload']['status'] ?? '');

        if (! $reference) return false;

        $transaction = PaymentTransaction::where('reference', $reference)->first();
        if (! $transaction) {
            Log::warning('OPay webhook: transaction not found', ['reference' => $reference]);
            return false;
        }

        if ($opayStatus === 'SUCCESS') {
            $this->markTransactionSuccess($transaction, $payload);
        } elseif (in_array($opayStatus, ['FAIL', 'CLOSE'], true)) {
            $transaction->update([
                'status'       => 'failed',
                'opay_payload' => $payload,
            ]);
        }

        return true;
    }

    /**
     * Initiate a refund via OPay.
     * Docs: https://documentation.opaycheckout.com/payment-refund
     *
     * @param  string $opayOrderNo   The original OPay orderNo from the payment
     * @param  float  $amount        Amount in Naira to refund
     * @param  string $refundRef     Your unique refund reference
     */
    public function refund(string $opayOrderNo, float $amount, string $refundRef): array
    {
        $payload = [
            'orderNo'    => $opayOrderNo,
            'refundAmount' => [
                'currency' => 'NGN',
                'total'    => (int) round($amount * 100), // kobo
            ],
            'refundReason' => 'Customer refund request',
            'reference'    => $refundRef,
            'country'      => 'NG',
        ];

        return $this->post('/api/v1/international/payment/refund', $payload);
    }

    /**
     * Query the status of a refund.
     * Docs: https://documentation.opaycheckout.com/payment-refund-status
     */
    public function queryRefund(string $refundRef): array
    {
        return $this->post('/api/v1/international/payment/queryrefund', [
            'reference' => $refundRef,
            'country'   => 'NG',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    /**
     * Generate a unique reference tied to the order.
     * Format: KS-{ORDER_REF}-{RANDOM}
     */
    private function generateReference(Order $order): string
    {
        return 'KS-' . $order->reference . '-' . strtoupper(Str::random(6));
    }

    /**
     * Verify an OPay webhook signature.
     * OPay signs the payload JSON with SHA-512 HMAC using your secret key.
     */
    private function verifyWebhookSignature(array $payloadData, string $receivedSignature): bool
    {
        // Never skip signature verification - an empty secret means webhook events are unverifiable.
        // Reject all webhook calls if the secret key is not configured.
        if (empty($this->secretKey)) {
            Log::error('OPay webhook rejected: OPAY_SECRET_KEY is not configured.');
            return false;
        }

        $json     = json_encode($payloadData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expected = hash_hmac('sha512', $json, $this->secretKey);

        return hash_equals($expected, $receivedSignature);
    }

    /**
     * Sign and POST to an OPay endpoint.
     */
    private function post(string $endpoint, array $data): array
    {
        $json      = json_encode($data, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha512', $json, $this->secretKey);

        $http = Http::timeout(30)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization'=> 'Bearer ' . $signature,
                'MerchantId'   => $this->merchantId,
            ]);

        // On local dev XAMPP, PHP lacks a CA bundle - skip SSL verification.
        // Never disable in production.
        if (config('app.env') !== 'production') {
            $http = $http->withoutVerifying();
        }

        $response = $http->post($this->baseUrl . $endpoint, $data);

        return $response->json() ?? [];
    }
}
