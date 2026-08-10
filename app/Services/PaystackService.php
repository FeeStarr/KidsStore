<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Notifications\PaymentUnderReviewNotification;
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
    private string $baseUrl;
    private int    $expireMinutes;

    public function __construct()
    {
        $this->secretKey     = (string) config('paystack.secret_key');
        $this->publicKey     = (string) config('paystack.public_key');
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
        $customerName = $order->customer?->name ?? 'Customer';
        $firstName = $order->customer?->profile?->first_name ?? '';
        $lastName = $order->customer?->profile?->last_name ?? '';
        $phone = $order->customer?->phone ?? $order->customer?->profile?->phone ?? null;

        // Fall back to splitting the name if profile names are empty
        if (empty(trim($firstName)) && empty(trim($lastName))) {
            $nameParts = explode(' ', trim($customerName), 2);
            $firstName = $nameParts[0] ?? $customerName;
            $lastName = $nameParts[1] ?? $firstName;
        }

        // Final safety — Paystack requires both first_name and last_name to be non-empty
        $firstName = trim($firstName) ?: 'Customer';
        $lastName = trim($lastName) ?: 'Customer';

        $customer = $this->getOrCreateCustomer($email, $firstName, $lastName, $phone);

        // Step 2: Assign a dedicated virtual account
        $payload = [
            'customer'    => $customer['id'],
            'currency'    => config('paystack.currency', 'NGN'),
        ];

        // Wema Bank only available in live mode for dedicated accounts
        $isTestKey = str_starts_with($this->secretKey, 'sk_test_');
        if (! $isTestKey) {
            $payload['preferred_bank'] = 'wema-bank';
        }

        $response = $this->post('/dedicated_account', $payload);

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

        // If already success, skip API call
        if ($transaction->status === 'success') {
            return $transaction->refresh();
        }

        $response = $this->resolveTransactionFromPaystack($transaction);

        if (! $response) {
            return $transaction->refresh();
        }

        $data = $response['data'];
        $paystackStatus = strtolower($data['status'] ?? '');

        if ($paystackStatus === 'success') {
            // Remember the Paystack identifiers for future queries
            $this->rememberTransactionIdentifiers($transaction, $data);

            $paystackAmount = (float) ($data['amount'] ?? 0) / 100;
            $orderAmount = (float) $transaction->order->grand_total;

            if ($paystackAmount < $orderAmount * 0.99 || $paystackAmount > $orderAmount * 1.01) {
                $this->markUnderReview($transaction, $response, 'Amount mismatch: expected ₦' . number_format($orderAmount, 2) . ', received ₦' . number_format($paystackAmount, 2));
            } else {
                $this->markTransactionSuccess($transaction, $response);
            }
        } elseif (in_array($paystackStatus, ['abandoned', 'failed', 'reversed'], true)) {
            $transaction->update([
                'status'       => 'failed',
                'opay_payload' => $response,
            ]);
        } elseif ($paystackStatus !== '') {
            $this->markUnderReview($transaction, $response, 'Paystack returned ambiguous status: ' . $paystackStatus);
        }

        return $transaction->refresh();
    }

    /**
     * Try to locate a Paystack transaction for the given local transaction.
     *
     * Resolution order:
     *   1. /transaction/{id}                 — stored Paystack transaction id
     *   2. /transaction/verify/{reference}   — Paystack reference stored from webhook/earlier lookup
     *   3. /transaction/verify/{appReference}— our KS- reference (usually 404)
     *   4. /transaction?customer={code}      — newest success for this customer matching the amount
     */
    private function resolveTransactionFromPaystack(PaymentTransaction $transaction): ?array
    {
        $txId       = $transaction->paystack_transaction_id ?? null;
        $paystackRef = $transaction->opay_payload['data']['reference'] ?? null;

        // 1. Transaction id
        if ($txId) {
            $response = $this->get("/transaction/{$txId}");
            if ($response['status'] ?? false) {
                return $response;
            }
        }

        // 2. Paystack reference
        if ($paystackRef) {
            $response = $this->get("/transaction/verify/{$paystackRef}");
            if ($response['status'] ?? false) {
                return $response;
            }
        }

        // 3. Our app reference
        $response = $this->get("/transaction/verify/{$transaction->reference}");
        if ($response['status'] ?? false) {
            return $response;
        }

        // 4. Customer-scoped transaction list, matched by amount
        $customerCode = $transaction->opay_payload['data']['customer']['customer_code'] ?? null;
        if ($customerCode) {
            try {
                $list = $this->get('/transaction?customer=' . urlencode($customerCode) . '&perPage=100');
                if ($list['status'] ?? false) {
                    foreach (($list['data'] ?? []) as $txn) {
                        if (strtolower($txn['status'] ?? '') === 'success') {
                            $amountNaira = (float) ($txn['amount'] ?? 0) / 100;
                            if (abs($amountNaira - $transaction->amount) < 1) {
                                return ['status' => true, 'data' => $txn];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Paystack queryStatus: customer lookup failed', ['transaction_id' => $transaction->id, 'error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /**
     * Persist the Paystack transaction id and reference on the local transaction
     * so subsequent queries can skip the expensive customer-list fallback.
     */
    private function rememberTransactionIdentifiers(PaymentTransaction $transaction, array $data): void
    {
        $txId = ! empty($data['id']) ? (string) $data['id'] : null;
        $ref  = ! empty($data['reference']) ? (string) $data['reference'] : null;

        $updates = [];
        if ($txId && (string) $transaction->paystack_transaction_id !== $txId) {
            $updates['paystack_transaction_id'] = $txId;
        }
        if ($ref && empty($transaction->opay_payload['data']['reference'])) {
            $payload = (array) $transaction->opay_payload;
            $payload['data']['reference'] = $ref;
            $updates['opay_payload'] = $payload;
        }

        if ($updates) {
            $transaction->update($updates);
        }
    }

    /**
     * Persist identifiers from an incoming webhook payload.
     */
    private function rememberWebhookIdentifiers(PaymentTransaction $transaction, array $data): void
    {
        $txId = $data['id'] ?? null;
        $ref  = $data['reference'] ?? null;

        $updates = [];
        if ($txId && (string) $transaction->paystack_transaction_id !== (string) $txId) {
            $updates['paystack_transaction_id'] = (string) $txId;
        }
        if ($ref && empty($transaction->opay_payload['data']['reference'])) {
            $payload = (array) $transaction->opay_payload;
            $payload['data']['reference'] = $ref;
            $updates['opay_payload'] = $payload;
        }

        if ($updates) {
            $transaction->update($updates);
        }
    }

    /**
     * Handle an incoming webhook from Paystack.
     * Verifies the signature, then updates the transaction + order.
     */
    public function handleWebhook(array $payload, string $signature, string $rawBody = ''): bool
    {
        $bodyForVerification = $rawBody ?: json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! $this->verifyWebhookSignatureRaw($bodyForVerification, $signature)) {
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
        $paystackId = $data['id'] ?? null;
        $webhookAcctNum = $data['authorization']['receiver_bank_account_number'] ?? null;

        $webhookAmount = isset($data['amount']) ? (float) $data['amount'] / 100 : null;

        $transaction = null;
        if ($reference) {
            $transaction = PaymentTransaction::where('reference', $reference)->first();
        }
        if (! $transaction && $webhookAcctNum) {
            $query = PaymentTransaction::where('virtual_account_number', $webhookAcctNum)
                ->where('status', 'pending');
            if ($webhookAmount !== null) {
                $query->whereRaw('ABS(amount - ?) < 1', [$webhookAmount]);
            }
            $transaction = $query->latest()->first();
        }
        if (! $transaction && $paystackId) {
            $transaction = PaymentTransaction::where('paystack_transaction_id', $paystackId)
                ->orWhere('opay_order_no', $paystackId)
                ->first();
        }

        // Tier 4: Query Paystack API with the webhook reference to get authorization details
        if (! $transaction && $reference) {
            try {
                $apiResult = $this->get("/transaction/verify/{$reference}");
                if ($apiResult['status'] ?? false) {
                    $apiData = $apiResult['data'] ?? [];
                    $apiAuth = $apiData['authorization'] ?? [];
                    $apiAcctNum = $apiAuth['receiver_bank_account_number'] ?? null;
                    $apiAcctNum2 = $apiAuth['account_number'] ?? null;

                    // Try matching by receiver bank account number + amount
                    if ($apiAcctNum) {
                        $q = PaymentTransaction::where('virtual_account_number', $apiAcctNum)
                            ->where('status', 'pending');
                        if ($webhookAmount !== null) {
                            $q->whereRaw('ABS(amount - ?) < 1', [$webhookAmount]);
                        }
                        $transaction = $q->latest()->first();
                    }
                    // Fallback: try matching by source account number + amount
                    if (! $transaction && $apiAcctNum2) {
                        $q = PaymentTransaction::where('virtual_account_number', $apiAcctNum2)
                            ->where('status', 'pending');
                        if ($webhookAmount !== null) {
                            $q->whereRaw('ABS(amount - ?) < 1', [$webhookAmount]);
                        }
                        $transaction = $q->latest()->first();
                    }
                    // Fallback: match by amount + pending status (last resort)
                    if (! $transaction && isset($apiData['amount'])) {
                        $amountNaira = (float) $apiData['amount'] / 100;
                        $transaction = PaymentTransaction::where('status', 'pending')
                            ->whereRaw('ABS(amount - ?) < 1', [$amountNaira])
                            ->latest()
                            ->first();
                    }

                    if ($transaction) {
                        Log::info('Paystack webhook: matched via Tier 4 API lookup', [
                            'transaction_id' => $transaction->id,
                            'reference' => $reference,
                            'api_acct_num' => $apiAcctNum,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Paystack webhook: Tier 4 API lookup failed', ['reference' => $reference, 'error' => $e->getMessage()]);
            }
        }

        if (! $transaction) {
            Log::warning('Paystack webhook: transaction not found', ['reference' => $reference, 'id' => $paystackId, 'acct' => $webhookAcctNum]);
            return false;
        }

        // Store Paystack's identifiers on the transaction for future queries
        $this->rememberWebhookIdentifiers($transaction, $data);

        $paystackStatus = strtolower($data['status'] ?? '');

        if ($paystackStatus === 'success') {
            $paystackAmount = (float) ($data['amount'] ?? 0) / 100;
            $orderAmount = (float) $transaction->order->grand_total;

            if ($paystackAmount < $orderAmount * 0.99 || $paystackAmount > $orderAmount * 1.01) {
                $this->markUnderReview($transaction, $payload, 'Amount mismatch via webhook: expected ₦' . number_format($orderAmount, 2) . ', received ₦' . number_format($paystackAmount, 2));
            } else {
                $this->markTransactionSuccess($transaction, $payload);
            }
        } elseif (in_array($paystackStatus, ['abandoned', 'failed', 'reversed'], true)) {
            $transaction->update([
                'status'       => 'failed',
                'opay_payload' => $payload,
            ]);
        } elseif ($paystackStatus !== '') {
            $this->markUnderReview($transaction, $payload, 'Webhook ambiguous status: ' . $paystackStatus);
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

    private function getOrCreateCustomer(string $email, string $firstName, string $lastName, ?string $phone = null): array
    {
        // Try to find existing customer by email
        $response = $this->get("/customer?email=" . urlencode($email));

        if (($response['status'] ?? false) && ! empty($response['data'])) {
            $customers = is_array($response['data']) ? $response['data'] : [$response['data']];
            if (! empty($customers)) {
                $existing = $customers[0];

                // If existing customer has empty first/last name or phone, update it
                $needsUpdate = empty(trim($existing['first_name'] ?? '')) || empty(trim($existing['last_name'] ?? ''));
                if ($phone && empty($existing['phone'])) {
                    $needsUpdate = true;
                }
                if ($needsUpdate) {
                    $customerCode = $existing['customer_code'] ?? null;
                    if ($customerCode) {
                        $updateData = [
                            'first_name' => $firstName,
                            'last_name'  => $lastName,
                        ];
                        if ($phone) {
                            $updateData['phone'] = $phone;
                        }
                        $this->put('/customer/' . $customerCode, $updateData);

                        $existing['first_name'] = $firstName;
                        $existing['last_name']  = $lastName;
                        if ($phone) {
                            $existing['phone'] = $phone;
                        }
                    }
                }

                return $existing;
            }
        }

        // Create new customer
        $payload = [
            'email'      => $email,
            'first_name' => $firstName,
            'last_name'  => $lastName,
        ];
        if ($phone) {
            $payload['phone'] = $phone;
        }

        $response = $this->post('/customer', $payload);

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
        if (! $order || $order->payment_status === 'paid') {
            return;
        }

        $paid = round((float) $order->amount_paid + (float) $transaction->amount, 2);
        $order->update([
            'amount_paid'    => $paid,
            'payment_status' => $paid >= (float) $order->grand_total ? 'paid' : 'partial',
        ]);

        // Auto-confirm pending-payment orders.
        // OrderService::confirm() decreases inventory and dispatches notifications.
        if ($order->payment_status === 'paid' && in_array($order->status, ['pending payment', 'pending confirmation', 'ordered'], true)) {
            try {
                app(OrderService::class)->confirm($order->fresh());
            } catch (\Throwable $e) {
                Log::error('Auto-confirm failed after payment', ['order' => $order->reference, 'error' => $e->getMessage()]);
            }
        }
    }

    private function markUnderReview(PaymentTransaction $transaction, array $rawPayload, string $reason): void
    {
        $transaction->update([
            'status'       => 'under_review',
            'opay_payload' => array_merge((array) $transaction->opay_payload, ['review_reason' => $reason]),
        ]);

        $order = $transaction->order;
        if ($order) {
            $order->update(['payment_status' => 'under_review']);

            // Notify all admin users
            $admins = User::where('is_active', true)
                ->whereIn('role', [User::ROLE_SUPERADMIN, User::ROLE_ADMIN])
                ->get();

            foreach ($admins as $index => $admin) {
                $admin->notify(new PaymentUnderReviewNotification($order, $transaction, $index === 0));
            }
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
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $this->verifyWebhookSignatureRaw($json, $receivedSignature);
    }

    private function verifyWebhookSignatureRaw(string $rawBody, string $receivedSignature): bool
    {
        if (empty($this->secretKey)) {
            Log::error('Paystack webhook rejected: PAYSTACK_SECRET_KEY is not configured.');
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, $this->secretKey);

        $match = hash_equals($expected, $receivedSignature);

        if (! $match) {
            Log::warning('Paystack webhook signature debug', [
                'received_sig'   => $receivedSignature,
                'expected_sig'   => $expected,
                'raw_body_len'   => strlen($rawBody),
                'raw_body_head'  => substr($rawBody, 0, 80),
                'key_prefix'     => substr($this->secretKey, 0, 8),
            ]);
        }

        return $match;
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

    /**
     * PUT to Paystack API.
     */
    private function put(string $endpoint, array $data): array
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

        $response = $http->put($this->baseUrl . $endpoint, $data);

        return $response->json() ?? [];
    }
}
