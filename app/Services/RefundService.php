<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundRequest;
use App\Models\User;
use App\Notifications\RefundStatusNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RefundService
{
    private const EVIDENCE_DIR = 'refund-evidence';

    public function __construct(private OPayService $opay)
    {
    }

    // ── Customer submits request ──────────────────────────────────────────────

    /**
     * Create a refund request for a full order or a specific item.
     *
     * @throws \RuntimeException if outside refund window or already requested
     */
    public function request(
        Order        $order,
        string       $reason,
        ?string      $details,
        ?OrderItem   $item     = null,
        int          $quantity = 1,
        ?UploadedFile $evidence = null
    ): RefundRequest {
        // Policy: only delivered orders can be refunded
        if ($order->status !== 'delivered') {
            throw new \RuntimeException('Refunds can only be requested for delivered orders.');
        }

        // Policy: within 7 days of becoming delivered (we use updated_at as delivery date proxy)
        $window = RefundRequest::REFUND_WINDOW_DAYS;
        if ($order->updated_at->diffInDays(now()) > $window) {
            throw new \RuntimeException("Refund window of {$window} days has passed.");
        }

        // Policy: no duplicate pending/approved request for the same scope
        $existingQuery = $order->refundRequests()
            ->whereIn('status', [RefundRequest::STATUS_PENDING, RefundRequest::STATUS_APPROVED]);
        if ($item) {
            $existingQuery->where('order_item_id', $item->id);
        } else {
            $existingQuery->whereNull('order_item_id');
        }
        if ($existingQuery->exists()) {
            throw new \RuntimeException('A refund request for this item is already pending.');
        }

        $amount = $item
            ? round((float) $item->unit_price * (1 - (float) $item->discount / 100) * $quantity, 2)
            : (float) $order->amount_paid;

        $evidencePath = null;
        if ($evidence) {
            $evidencePath = $evidence->store(self::EVIDENCE_DIR, 'public');
        }

        return RefundRequest::create([
            'order_id'      => $order->id,
            'order_item_id' => $item?->id,
            'quantity'      => $quantity,
            'amount'        => $amount,
            'status'        => RefundRequest::STATUS_PENDING,
            'reason'        => $reason,
            'details'       => $details,
            'evidence_path' => $evidencePath,
        ]);
    }

    // ── Admin approves ────────────────────────────────────────────────────────

    /**
     * Approve and process the refund via OPay.
     */
    public function approve(RefundRequest $refundRequest, User $admin, ?string $note = null): RefundRequest
    {
        if (! $refundRequest->isPending()) {
            throw new \RuntimeException('Only pending requests can be approved.');
        }

        // Find the original OPay transaction for this order
        $transaction = $refundRequest->order->paymentTransactions()
            ->where('status', 'success')
            ->latest()
            ->first();

        return DB::transaction(function () use ($refundRequest, $admin, $note, $transaction) {
            $refundRequest->update([
                'status'      => RefundRequest::STATUS_APPROVED,
                'admin_note'  => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            // Attempt OPay refund if we have a transaction
            if ($transaction?->opay_order_no) {
                try {
                    $result = $this->opay->refund(
                        $transaction->opay_order_no,
                        $refundRequest->amount,
                        $refundRequest->order->reference . '-R' . $refundRequest->id
                    );

                    $opayStatus = strtoupper($result['code'] ?? '');
                    $success    = $opayStatus === '00000';

                    $refundRequest->update([
                        'status'        => $success ? RefundRequest::STATUS_REFUNDED : RefundRequest::STATUS_FAILED,
                        'opay_refund_no'=> $result['data']['refundOrderNo'] ?? null,
                        'opay_payload'  => $result,
                    ]);

                    // Update order payment status on full refund
                    if ($success && ! $refundRequest->order_item_id) {
                        $refundRequest->order->update(['payment_status' => 'refunded']);
                    }
                } catch (\Throwable $e) {
                    Log::error('OPay refund failed', ['error' => $e->getMessage(), 'request' => $refundRequest->id]);
                    $refundRequest->update(['status' => RefundRequest::STATUS_FAILED]);
                }
            } else {
                // No OPay transaction (e.g. cash payment) — mark as refunded manually
                $refundRequest->update([
                    'status' => RefundRequest::STATUS_REFUNDED,
                    'admin_note' => ($note ? $note . "\n" : '') . '[Manual refund — no OPay transaction]',
                ]);
                if (! $refundRequest->order_item_id) {
                    $refundRequest->order->update(['payment_status' => 'refunded']);
                }
            }

            return $refundRequest->refresh();
        });

        // Notify customer outside transaction
        $this->notifyCustomer($refundRequest->refresh());

        return $refundRequest;
    }

    // ── Admin rejects ─────────────────────────────────────────────────────────

    public function reject(RefundRequest $refundRequest, User $admin, string $note): RefundRequest
    {
        if (! $refundRequest->isPending()) {
            throw new \RuntimeException('Only pending requests can be rejected.');
        }

        $refundRequest->update([
            'status'      => RefundRequest::STATUS_REJECTED,
            'admin_note'  => $note,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->notifyCustomer($refundRequest->refresh());

        return $refundRequest->refresh();
    }

    private function notifyCustomer(RefundRequest $refundRequest): void
    {
        try {
            $customer = $refundRequest->order->customer;
            if ($customer) {
                $customer->notify(new RefundStatusNotification($refundRequest));
            }
        } catch (\Throwable $e) {
            Log::error('RefundStatus notification failed', ['error' => $e->getMessage(), 'request' => $refundRequest->id]);
        }
    }
}
