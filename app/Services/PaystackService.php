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
 * Paystack integration — standard transaction (popup / redirect checkout).
 *
 * Docs: https://paystack.com/docs/api/#transaction-initialize
 *       https://paystack.com/docs/payments/accept-payments/#popup
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
    private string $webhookSecret;

    public function __construct()
    {
        $this->secretKey     = (string) config('paystack.secret_key');
        $this->publicKey     = (string) config('paystack.public_key');
        $this->baseUrl       = (string) config('paystack.base_url');
        $this->expireMinutes = (int) config('paystack.expire_minutes', 45);
        $this->webhookSecret = (string) config('paystack.webhook_secret', '');
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Initialize a standard Paystack transaction for the order.
     *
     * Our own KS- reference is passed to Paystack as the transaction reference,
     * which means the webhook tier-1 lookup and queryStatus tier-3 verify will
     * both match directly — no virtual account or customer-scoped fallback needed.
     *
     * @param  string|null  $callbackUrl  Override callback URL (guest vs registered)
     * @throws \RuntimeException if Paystack returns an error
     */
    public function initiate(Order $order, ?string $callbackUrl = null): PaymentTransaction
    {
        // Expire any previous pending transactions for this order
        $order->paymentTransactions()
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $reference = $this->generateReference($order);

        // ── Stub mode: no credentials configured ─────────────────────────────
        if (empty($this->secretKey)) {
            return PaymentTransaction::create([
                'order_id'     => $order->id,
                'reference'    => $reference,
                'opay_order_no'=> null,
                'amount'       => (float) $order->grand_total,
                'status'       => 'pending',
                'expires_at'   => now()->addMinutes($this->expireMinutes),
                'opay_payload' => ['note' => 'Stub — PAYSTACK_SECRET_KEY not set'],
            ]);
        }
        // ─────────────────────────────────────────────────────────────────────

        $amountKobo = (int) round((float) $order->grand_total * 100);
        $email = $order->customer?->email ?? $order->guest_email ?? 'customer@kidsstore.com';

        // Use provided callback URL, or determine from order type
        if (! $callbackUrl) {
            $callbackUrl = $order->customer_id
                ? route('shop.paystack.callback', $order)
                : route('shop.paystack.guest-callback', $order->lookup_token);
        }

        // Paystack treats reference as globally unique forever — retry with a fresh
        // reference if Paystack reports duplicate (random collision or stale retry).
        $response = null;
        $attempts = 0;
        do {
            $attempts++;
            $response = $this->post('/transaction/initialize', [
                'email'        => $email,
                'amount'       => $amountKobo,
                'reference'    => $reference,
                'currency'     => config('paystack.currency', 'NGN'),
                'callback_url' => $callbackUrl,
                'metadata'     => [
                    'order_id'      => $order->id,
                    'order_ref'     => $order->reference,
                    'customer_id'   => $order->customer_id,
                    'cancel_action' => $order->customer_id
                        ? route('shop.account.orders.show', $order)
                        : route('shop.order.track', $order->lookup_token),
                ],
            ]);

            if ($response['status'] ?? false) {
                break;
            }

            $msg = $response['message'] ?? 'Unknown Paystack error';
            $isDuplicate = str_contains(strtolower($msg), 'duplicate');

            if ($isDuplicate && $attempts < 3) {
                Log::warning('Paystack duplicate reference — retrying with new reference', [
                    'order' => $order->reference,
                    'old_reference' => $reference,
                    'attempt' => $attempts,
                ]);
                $reference = $this->generateReference($order->fresh() ?? $order);
                continue;
            }

            Log::error('Paystack initiate failed', ['order' => $order->reference, 'response' => $response]);
            throw new \RuntimeException("Payment initiation failed: {$msg}");
        } while ($attempts < 3);

        $data = $response['data'];

        $transaction = PaymentTransaction::create([
            'order_id'     => $order->id,
            'reference'    => $reference,
            'opay_order_no'=> $data['reference'] ?? $reference,
            'amount'       => (float) $order->grand_total,
            'status'       => 'pending',
            'expires_at'   => now()->addMinutes($this->expireMinutes),
            'opay_payload' => $response,
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

            // Amount must match to the kobo. Any deviation is an anomaly that
            // must NOT auto-confirm — route it to manual review instead.
            if (abs($paystackAmount - $orderAmount) > 0.01) {
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
     *   4. /transaction?customer={numeric id} — unclaimed success for this customer
     *                                             matching amount + closest time
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

        // 4. Customer-scoped transaction list (numeric customer id), matched by
        //    amount + excluding already-claimed transactions + closest time.
        $customerId = $transaction->opay_payload['customer_id']
            ?? $transaction->opay_payload['data']['customer']['id']
            ?? null;

        // Legacy transactions were created before the customer id was stored.
        // Resolve it from the order's customer email so those pending payments
        // can still be matched.
        if (! $customerId && $transaction->order?->customer) {
            try {
                $customer = $transaction->order->customer;
                $customer = $this->getOrCreateCustomer(
                    $customer->email,
                    $customer->profile?->first_name ?: ($customer->name ?? 'Customer'),
                    $customer->profile?->last_name ?: 'Customer'
                );
                $customerId = $customer['id'] ?? null;
                if ($customerId) {
                    $payload = (array) $transaction->opay_payload;
                    $payload['customer_id'] = $customerId;
                    $transaction->update(['opay_payload' => $payload]);
                }
            } catch (\Exception $e) {
                Log::warning('Paystack queryStatus: failed to resolve customer id', ['transaction_id' => $transaction->id, 'error' => $e->getMessage()]);
            }
        }

        if ($customerId) {
            try {
                $list = $this->get('/transaction?customer=' . urlencode($customerId) . '&perPage=100');
                if ($list['status'] ?? false) {
                    // Identifiers already claimed by other local transactions
                    $claimed = [];
                    foreach (PaymentTransaction::where('id', '!=', $transaction->id)->get() as $other) {
                        if (! empty($other->paystack_transaction_id)) {
                            $claimed[] = (string) $other->paystack_transaction_id;
                        }
                        if (! empty($other->opay_payload['data']['reference'])) {
                            $claimed[] = (string) $other->opay_payload['data']['reference'];
                        }
                    }
                    $claimed = array_flip($claimed);

                    $localCreated = $transaction->created_at;
                    $localAcctNum = $transaction->virtual_account_number;
                    $best = null;
                    $bestReceiverMatch = false;
                    $bestDiff = PHP_INT_MAX;
                    $checked = 0;
                    $rejectedAcct = 0;
                    $rejectedAmount = 0;

                    foreach (($list['data'] ?? []) as $txn) {
                        if (strtolower($txn['status'] ?? '') !== 'success') {
                            continue;
                        }

                        $checked++;

                        $payRef = $txn['reference'] ?? null;
                        $payId  = isset($txn['id']) ? (string) $txn['id'] : null;
                        if ($payRef && isset($claimed[$payRef])) {
                            continue;
                        }
                        if ($payId && isset($claimed[$payId])) {
                            continue;
                        }

                        // A payment must have been made AFTER this payment
                        // session/account was provisioned — an older transfer
                        // belongs to a previous order, never this one.
                        $created = isset($txn['created_at'])
                            ? \Carbon\Carbon::parse($txn['created_at'])
                            : null;
                        if (! $created || $created->lt($localCreated)) {
                            continue;
                        }

                        // Amount must match to the kobo — a similar-amount
                        // payment for another order must never auto-confirm.
                        // (Verified before the receiver preference so we do not
                        // rely on an account field Paystack may not always send.)
                        $amountNaira = (float) ($txn['amount'] ?? 0) / 100;
                        if (abs($amountNaira - $transaction->amount) > 0.01) {
                            $rejectedAmount++;
                            continue;
                        }

                        // The receiver account is the strongest signal when
                        // Paystack reports it, but it is NOT guaranteed on every
                        // charge. Prefer an exact receiver match; fall back to
                        // amount+time if the field is absent or inconsistent.
                        $receiverAcct = $txn['authorization']['receiver_bank_account_number'] ?? null;
                        $receiverMatches = (! $receiverAcct || ! $localAcctNum)
                            ? null
                            : (string) $receiverAcct === (string) $localAcctNum;
                        if ($receiverMatches === false) {
                            $rejectedAcct++;
                        }

                        $diff = abs($created->diffInMinutes($localCreated));

                        if ($best === null
                            || ($receiverMatches === true && ! $bestReceiverMatch)
                            || ($receiverMatches === $bestReceiverMatch && $diff < $bestDiff)) {
                            $best = $txn;
                            $bestReceiverMatch = $receiverMatches === true;
                            $bestDiff = $diff;
                        }
                    }

                    if ($best) {
                        return ['status' => true, 'data' => $best];
                    }

                    Log::info('Paystack queryStatus: no unclaimed success payment matched', [
                        'transaction_id'   => $transaction->id,
                        'customer_id'      => $customerId,
                        'success_checked'  => $checked,
                        'rejected_acct'    => $rejectedAcct,
                        'rejected_amount'  => $rejectedAmount,
                        'local_amount'     => $transaction->amount,
                        'local_created_at' => $localCreated ? $localCreated->toISOString() : null,
                        'virtual_account'  => $localAcctNum,
                    ]);
                } else {
                    Log::warning('Paystack queryStatus: customer txn lookup failed', [
                        'transaction_id' => $transaction->id,
                        'customer_id'    => $customerId,
                        'response'       => $list['message'] ?? $list,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Paystack queryStatus: customer lookup failed', ['transaction_id' => $transaction->id, 'error' => $e->getMessage()]);
            }
        } else {
            Log::warning('Paystack queryStatus: no customer id on transaction', ['transaction_id' => $transaction->id]);
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
                    // Fallback: match by amount + pending status (last resort, exact amount)
                    if (! $transaction && isset($apiData['amount'])) {
                        $amountNaira = round((float) $apiData['amount'] / 100, 2);
                        $transaction = PaymentTransaction::where('status', 'pending')
                            ->where('amount', (string) $amountNaira)
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

            // Exact amount required — a same-amount charge for a different order
            // must never confirm this one; flag for review instead.
            if (abs($paystackAmount - $orderAmount) > 0.01) {
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
        \Illuminate\Support\Facades\DB::transaction(function () use ($transaction, $rawPayload) {
            $transaction->update([
                'status'       => 'success',
                'opay_payload' => $rawPayload,
            ]);

            // Update the parent order with row-level lock to prevent race conditions
            $order = $transaction->order;
            if (! $order) {
                return;
            }

            $order = Order::lockForUpdate()->find($order->id);
            if ($order->payment_status === 'paid') {
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
        });
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
        // Must be globally unique for Paystack — include order id (never re-used),
        // timestamp and strong random. Previous KS-{ORD-ref}-{6} could collide when
        // ORD-ref itself collided under concurrent checkouts (max(id)+1 race).
        return 'KS-' . $order->id . '-' . $order->reference . '-' . time() . '-' . strtoupper(Str::random(8));
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
        // Paystack supports two signing modes:
        // 1. Webhook secret (PAYSTACK_WEBHOOK_SECRET) — preferred, set in dashboard
        // 2. Secret key (PAYSTACK_SECRET_KEY) — legacy fallback
        // Try webhook secret first; fall back to secret key for backward compat.
        $keys = array_filter([$this->webhookSecret, $this->secretKey]);

        if (empty($keys)) {
            Log::error('Paystack webhook rejected: neither PAYSTACK_WEBHOOK_SECRET nor PAYSTACK_SECRET_KEY is configured.');
            return false;
        }

        foreach ($keys as $key) {
            $expected = hash_hmac('sha512', $rawBody, $key);
            if (hash_equals($expected, $receivedSignature)) {
                return true;
            }
        }

        Log::warning('Paystack webhook signature mismatch', [
            'received_sig'   => $receivedSignature,
            'tried_keys'     => count($keys),
            'raw_body_len'   => strlen($rawBody),
            'raw_body_head'  => substr($rawBody, 0, 80),
        ]);

        return false;
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
