<?php

namespace App\Services;

use App\Helpers\BusinessDayHelper;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupStation;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use App\Models\ReturnAuditLog;
use App\Models\User;
use App\Notifications\RefundReturnNotification;
use App\Notifications\RefundStatusNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RefundService
{
    private const EVIDENCE_DIR = 'evidence/refunds';
    private const EVIDENCE_VIDEO_DIR = 'evidence/refunds/videos';

    public function __construct(
        private PaystackService $paystack,
        private InventoryService $inventory,
    ) {
    }

    // ── Customer submits request ──────────────────────────────────────────────

    /**
     * Create a return request for a full order or a specific item.
     *
     * @throws \RuntimeException if validation fails
     */
    public function request(
        Order        $order,
        string       $reason,
        ?string      $details,
        ?OrderItem   $item             = null,
        int          $quantity         = 1,
        ?UploadedFile $evidence        = null,
        ?ProductVariant $exchangeVariant = null
    ): RefundRequest {
        // Policy: only delivered orders can be refunded
        if ($order->status !== 'delivered') {
            throw new \RuntimeException('Refunds can only be requested for delivered orders.');
        }

        // Policy: within return window (per-reason time limits)
        $limitHours = RefundRequest::REASON_TIME_LIMITS[$reason] ?? (RefundRequest::REFUND_WINDOW_DAYS * 24);
        if ($order->updated_at->diffInHours(now()) > $limitHours) {
            $limitDays = round($limitHours / 24, 1);
            throw new \RuntimeException("Return window of {$limitDays} days for this reason has passed.");
        }

        // Policy: non-returnable items cannot be refunded
        if ($item && $item->product && ! $item->product->is_returnable) {
            throw new \RuntimeException('This product is not eligible for returns or refunds.');
        }

        if (! $item) {
            $allNonReturnable = $order->items()
                ->with('product')
                ->get()
                ->every(fn ($i) => $i->product && ! $i->product->is_returnable);
            if ($allNonReturnable && $order->items->isNotEmpty()) {
                throw new \RuntimeException('None of the items in this order are eligible for returns or refunds.');
            }
        }

        // Exchange: validate the requested replacement variant
        if ($exchangeVariant) {
            if (! $item) {
                throw new \RuntimeException('Exchange requests require a specific item to be returned.');
            }
            if (! $item->product_id || $exchangeVariant->product_id !== $item->product_id) {
                throw new \RuntimeException('The replacement variant must be for the same product.');
            }
            if (! $exchangeVariant->is_active) {
                throw new \RuntimeException('The selected replacement variant is no longer available.');
            }
            if ($exchangeVariant->stock_quantity < $quantity) {
                throw new \RuntimeException('The selected replacement variant does not have enough stock.');
            }
        }

        // Policy: no duplicate active request for the same scope
        $existingQuery = $order->refundRequests()
            ->whereIn('status', RefundRequest::ACTIVE_STATUSES);
        if ($item) {
            $existingQuery->where('order_item_id', $item->id);
        } else {
            $existingQuery->whereNull('order_item_id');
        }
        if ($existingQuery->exists()) {
            throw new \RuntimeException('A return request for this item is already in progress.');
        }

        $itemPrice = $item
            ? round((float) $item->unit_price * (1 - (float) $item->discount / 100) * $quantity, 2)
            : (float) $order->amount_paid;

        $includeShipping = in_array($reason, RefundRequest::SHIPPING_REFUND_REASONS);

        if ($item && $includeShipping) {
            $shippingRefundBeforeDiscount = (float) $order->shipping_fee;
            $shippingDiscountPct = (float) \App\Models\Setting::get('shipping_discount', 0);
            $shippingRefund = $shippingRefundBeforeDiscount * (1 - $shippingDiscountPct / 100);
            $amount = round($itemPrice + $shippingRefund, 2);
        } else {
            $amount = $itemPrice;
        }

        $evidencePath = null;
        if ($evidence) {
            $evidencePath = $this->storeInPublic($evidence, self::EVIDENCE_DIR);
        }

        // Determine initial status based on evidence requirements
        $evidenceRules = RefundRequest::EVIDENCE_RULES[$reason] ?? [];
        $photosRequired = ($evidenceRules['photos'] ?? 'optional') === 'required';
        $commentsRequired = ($evidenceRules['comments'] ?? 'optional') === 'required';
        $hasEvidence = $evidencePath !== null;
        $hasComments = trim((string) $details) !== '';

        // If mandatory evidence is missing, set to awaiting_evidence
        if ($photosRequired && ! $hasEvidence) {
            $initialStatus = RefundRequest::STATUS_AWAITING_EVIDENCE;
        } elseif ($commentsRequired && ! $hasComments) {
            $initialStatus = RefundRequest::STATUS_AWAITING_EVIDENCE;
        } else {
            $initialStatus = RefundRequest::STATUS_REQUESTED;
        }

        $refund = RefundRequest::create([
            'order_id'            => $order->id,
            'order_item_id'       => $item?->id,
            'exchange_variant_id' => $exchangeVariant?->id,
            'quantity'            => $quantity,
            'amount'              => $amount,
            'status'              => $initialStatus,
            'reason'              => $reason,
            'details'             => $details,
            'evidence_path'       => $evidencePath,
            'review_deadline'     => BusinessDayHelper::slaDeadline(now(), 6),
        ]);

        $this->logAudit($refund, 'requested', null, "Reason: {$reason}", [
            'scope' => $item ? 'item' : 'full',
            'quantity' => $quantity,
            'initial_status' => $initialStatus,
            'exchange_variant_id' => $exchangeVariant?->id,
            'exchange_variant_label' => $exchangeVariant?->options_label,
        ]);

        $this->notifyCustomer($refund->refresh());

        return $refund;
    }

    // ── Customer uploads additional evidence ──────────────────────────────────

    /**
     * Upload additional evidence for a return request that is awaiting evidence.
     */
    public function uploadEvidence(
        RefundRequest $refundRequest,
        ?UploadedFile $photo = null,
        ?UploadedFile $video = null,
        ?string       $details = null
    ): RefundRequest {
        if ($refundRequest->status !== RefundRequest::STATUS_AWAITING_EVIDENCE) {
            throw new \RuntimeException('This request is not awaiting evidence.');
        }

        $updates = ['status' => RefundRequest::STATUS_REQUESTED];

        if ($photo) {
            $updates['evidence_path'] = $this->storeInPublic($photo, self::EVIDENCE_DIR);
        }

        if ($video) {
            $updates['evidence_video_path'] = $this->storeInPublic($video, self::EVIDENCE_VIDEO_DIR);
        }

        if ($details !== null) {
            $updates['details'] = $details;
        }

        $refundRequest->update($updates);
        $this->logAudit($refundRequest, 'evidence_uploaded', null, 'Additional evidence uploaded');

        // Notify admin + super admin + customer care that evidence has been uploaded
        $this->notifyEvidenceUploaded($refundRequest->refresh());

        return $refundRequest->refresh();
    }

    // ── Admin: request evidence ───────────────────────────────────────────────

    /**
     * Admin requests additional evidence from customer.
     */
    public function requestEvidence(RefundRequest $refundRequest, User $admin, ?string $note = null): RefundRequest
    {
        if (! $refundRequest->isPending()) {
            throw new \RuntimeException('Only pending requests can have evidence requested.');
        }

        $refundRequest->update([
            'status'     => RefundRequest::STATUS_AWAITING_EVIDENCE,
            'admin_note' => $note,
        ]);

        $this->logAudit($refundRequest, 'evidence_requested', $admin->id, $note);
        $this->notifyCustomer($refundRequest->refresh());

        return $refundRequest->refresh();
    }

    // ── Admin approves ────────────────────────────────────────────────────────

    /**
     * Approve the return request. This moves to approved status.
     * Stock is NOT restored yet — that happens when the item is received.
     */
    public function approve(RefundRequest $refundRequest, User $admin, ?string $note = null): RefundRequest
    {
        if (! $refundRequest->isPending()) {
            throw new \RuntimeException('Only pending requests can be approved.');
        }

        $pickupStationId = $refundRequest->order->pickup_station_id;

        $refundRequest->update([
            'status'            => RefundRequest::STATUS_APPROVED,
            'admin_note'        => $note,
            'reviewed_by'       => $admin->id,
            'reviewed_at'       => now(),
            'pickup_station_id' => $pickupStationId,
            'dropoff_deadline'  => BusinessDayHelper::slaDeadline(now(), 3),
        ]);

        $this->logAudit($refundRequest, 'approved', $admin->id, $note);
        $this->notifyCustomer($refundRequest->refresh());

        // Notify the pickup station if the order has one
        if ($pickupStationId) {
            $this->notifyPickupStation($refundRequest->refresh());
        }

        return $refundRequest->refresh();
    }

    // ── Pickup station collects return ────────────────────────────────────────

    /**
     * Pickup station marks an approved return as collected from the customer.
     */
    public function collectReturn(RefundRequest $refundRequest, int $stationId): RefundRequest
    {
        if ($refundRequest->status !== RefundRequest::STATUS_APPROVED) {
            throw new \RuntimeException('Only approved returns can be collected.');
        }

        if ($refundRequest->pickup_station_id !== $stationId) {
            throw new \RuntimeException('This return is not assigned to your station.');
        }

        $refundRequest->update([
            'status'              => RefundRequest::STATUS_RETURN_COLLECTED,
            'return_collected_at' => now(),
        ]);

        $this->logAudit($refundRequest, 'return_collected', null, "Collected at station #{$stationId}");

        // Notify admin + super admin + customer care that item has been collected
        $this->notifyReturnCollected($refundRequest->refresh());

        return $refundRequest->refresh();
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

        $this->logAudit($refundRequest, 'rejected', $admin->id, $note);
        $this->notifyCustomer($refundRequest->refresh());

        return $refundRequest->refresh();
    }

    // ── Admin marks item received ─────────────────────────────────────────────

    /**
     * Mark the returned item as received. Triggers stock restoration.
     */
    public function markReceived(RefundRequest $refundRequest, User $admin, ?string $note = null): RefundRequest
    {
        $validStatuses = [
            RefundRequest::STATUS_AWAITING_SHIPMENT,
            RefundRequest::STATUS_IN_TRANSIT,
            RefundRequest::STATUS_APPROVED,
        ];
        if (! in_array($refundRequest->status, $validStatuses, true)) {
            throw new \RuntimeException('Cannot mark as received from current status.');
        }

        $refundRequest->update([
            'status'              => RefundRequest::STATUS_RECEIVED,
            'inspection_deadline' => BusinessDayHelper::slaDeadline(now(), 5),
        ]);

        // Restore stock when item is received
        $this->restoreStock($refundRequest);

        $this->logAudit($refundRequest, 'item_received', $admin->id, $note);
        $this->notifyCustomer($refundRequest->refresh());

        return $refundRequest->refresh();
    }

    // ── Admin: complete inspection ────────────────────────────────────────────

    /**
     * Complete inspection and move to refund approved or replacement approved.
     */
    public function inspect(
        RefundRequest $refundRequest,
        User          $admin,
        string        $outcome, // 'refund' or 'replacement'
        ?string       $notes = null
    ): RefundRequest {
        if ($refundRequest->status !== RefundRequest::STATUS_RECEIVED) {
            throw new \RuntimeException('Item must be received before inspection.');
        }

        // For exchange requests, validate replacement variant stock before approving
        if ($outcome === 'replacement' && $refundRequest->exchangeVariant) {
            $exchangeVariant = $refundRequest->exchangeVariant;
            $quantity = $refundRequest->quantity ?? 1;

            if (! $exchangeVariant->is_active) {
                throw new \RuntimeException('The replacement variant is no longer available.');
            }
            if ($exchangeVariant->stock_quantity < $quantity) {
                throw new \RuntimeException("Insufficient stock for the replacement variant. Available: {$exchangeVariant->stock_quantity}, needed: {$quantity}.");
            }

            // Deduct stock for the replacement variant
            $this->inventory->deductForExchange(
                $exchangeVariant,
                $quantity,
                RefundRequest::class,
                $refundRequest->id,
                "Exchange for return #{$refundRequest->id}: {$refundRequest->reason}"
            );
        }

        $newStatus = $outcome === 'replacement'
            ? RefundRequest::STATUS_REPLACEMENT_APPROVED
            : RefundRequest::STATUS_REFUND_APPROVED;

        $refundRequest->update([
            'status'           => $newStatus,
            'inspection_notes' => $notes,
            'inspected_by'     => $admin->id,
            'inspected_at'     => now(),
        ]);

        $this->logAudit($refundRequest, 'inspection_completed', $admin->id, "Outcome: {$outcome}. {$notes}", [
            'exchange_variant_id' => $refundRequest->exchange_variant_id,
            'exchange_variant_label' => $refundRequest->exchangeVariant?->options_label,
        ]);
        $this->notifyCustomer($refundRequest->refresh());

        return $refundRequest->refresh();
    }

    // ── Admin: mark replacement shipped ───────────────────────────────────────

    /**
     * Mark the replacement as shipped.
     */
    public function markReplacementShipped(RefundRequest $refundRequest, User $admin, ?string $note = null): RefundRequest
    {
        if ($refundRequest->status !== RefundRequest::STATUS_REPLACEMENT_APPROVED) {
            throw new \RuntimeException('Only approved replacements can be marked as shipped.');
        }

        $refundRequest->update([
            'status'     => RefundRequest::STATUS_REPLACEMENT_SHIPPED,
            'admin_note' => $note,
        ]);

        $this->logAudit($refundRequest, 'replacement_shipped', $admin->id, $note);
        $this->notifyCustomer($refundRequest->refresh());

        return $refundRequest->refresh();
    }

    // ── Admin: process refund ─────────────────────────────────────────────────

    /**
     * Process the actual refund via OPay after inspection approval.
     */
    public function processRefund(RefundRequest $refundRequest, User $admin, ?string $note = null): RefundRequest
    {
        if ($refundRequest->status !== RefundRequest::STATUS_REFUND_APPROVED) {
            throw new \RuntimeException('Only inspection-approved refunds can be processed.');
        }

        $transaction = $refundRequest->order->paymentTransactions()
            ->where('status', 'success')
            ->latest()
            ->first();

        $result = DB::transaction(function () use ($refundRequest, $admin, $note, $transaction) {
            $refundRequest->update([
                'status'     => RefundRequest::STATUS_REFUND_PROCESSING,
                'admin_note' => $note,
            ]);

            $this->logAudit($refundRequest, 'refund_processing', $admin->id, $note);

            if ($transaction?->opay_order_no) {
                try {
                    $opResult = $this->paystack->refund(
                        $transaction->reference,
                        $refundRequest->amount,
                        $refundRequest->order->reference . '-R' . $refundRequest->id
                    );

                    $paystackStatus = $opResult['status'] ?? false;
                    $success = $paystackStatus === true;

                    $refundRequest->update([
                        'status'         => $success ? RefundRequest::STATUS_REFUNDED : RefundRequest::STATUS_REFUND_FAILED,
                        'opay_refund_no' => $opResult['data']['refund_reference'] ?? null,
                        'opay_payload'   => $opResult,
                    ]);

                    $this->logAudit($refundRequest, $success ? 'refund_completed' : 'refund_failed', $admin->id);

                    if ($success && ! $refundRequest->order_item_id) {
                        $refundRequest->order->update(['payment_status' => 'refunded']);
                    }
                } catch (\Throwable $e) {
                    Log::error('Paystack refund failed', ['error' => $e->getMessage(), 'request' => $refundRequest->id]);
                    $refundRequest->update(['status' => RefundRequest::STATUS_REFUND_FAILED]);
                    $this->logAudit($refundRequest, 'refund_failed', $admin->id, $e->getMessage());
                }
            } else {
                $refundRequest->update([
                    'status'     => RefundRequest::STATUS_REFUNDED,
                    'admin_note' => ($note ? $note . "\n" : '') . '[Manual refund — no Paystack transaction]',
                ]);
                $this->logAudit($refundRequest, 'refund_completed', $admin->id, 'Manual refund');
                if (! $refundRequest->order_item_id) {
                    $refundRequest->order->update(['payment_status' => 'refunded']);
                }
            }

            return $refundRequest->refresh();
        });

        $this->notifyCustomer($result);

        return $result;
    }

    // ── Customer cancels ──────────────────────────────────────────────────────

    /**
     * Customer cancels their return request (only if still pending).
     */
    public function cancel(RefundRequest $refundRequest, User $customer): RefundRequest
    {
        $cancellableStatuses = [
            RefundRequest::STATUS_REQUESTED,
            RefundRequest::STATUS_PENDING_REVIEW,
            RefundRequest::STATUS_AWAITING_EVIDENCE,
        ];
        if (! in_array($refundRequest->status, $cancellableStatuses, true)) {
            throw new \RuntimeException('This return request cannot be cancelled.');
        }

        $refundRequest->update(['status' => RefundRequest::STATUS_CANCELLED]);
        $this->logAudit($refundRequest, 'cancelled', $customer->id, 'Cancelled by customer');
        $this->notifyCustomer($refundRequest->refresh());

        return $refundRequest->refresh();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function restoreStock(RefundRequest $refundRequest): void
    {
        $order = $refundRequest->order;
        $quantity = $refundRequest->quantity ?? 1;

        if ($refundRequest->order_item_id) {
            $item = $refundRequest->orderItem;
            if ($item && $item->variant) {
                $this->inventory->restoreFromReturn(
                    $item->variant,
                    $quantity,
                    RefundRequest::class,
                    $refundRequest->id,
                    "Return: {$refundRequest->reason}"
                );
            }
        } else {
            foreach ($order->items as $item) {
                if ($item->variant) {
                    $this->inventory->restoreFromReturn(
                        $item->variant,
                        $item->quantity,
                        RefundRequest::class,
                        $refundRequest->id,
                        "Full order return: {$refundRequest->reason}"
                    );
                }
            }
        }
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

    private function notifyPickupStation(RefundRequest $refundRequest): void
    {
        try {
            $station = $refundRequest->pickupStation;
            if ($station && $station->email) {
                \Illuminate\Support\Facades\Notification::route('mail', $station->email)
                    ->notify(new RefundReturnNotification($refundRequest));
            }
        } catch (\Throwable $e) {
            Log::error('RefundReturn notification to station failed', ['error' => $e->getMessage(), 'request' => $refundRequest->id]);
        }
    }

    private function notifyReturnCollected(RefundRequest $refundRequest): void
    {
        try {
            // Notify admin + super admin + customer care via the existing status notification
            $admins = \App\Notifications\NotificationRecipients::adminUsers();
            foreach ($admins as $admin) {
                $admin->notify(new RefundStatusNotification($refundRequest));
            }

            // Also notify customer support staff
            $support = \App\Notifications\NotificationRecipients::customerSupportStaff();
            foreach ($support as $staff) {
                $staff->notify(new RefundStatusNotification($refundRequest));
            }
        } catch (\Throwable $e) {
            Log::error('Return collected notification failed', ['error' => $e->getMessage(), 'request' => $refundRequest->id]);
        }
    }

    private function notifyEvidenceUploaded(RefundRequest $refundRequest): void
    {
        try {
            // Notify admins
            $admins = \App\Notifications\NotificationRecipients::adminUsers();
            foreach ($admins as $admin) {
                $admin->notify(new RefundStatusNotification($refundRequest));
            }

            // Notify customer support staff
            $support = \App\Notifications\NotificationRecipients::customerSupportStaff();
            foreach ($support as $staff) {
                $staff->notify(new RefundStatusNotification($refundRequest));
            }
        } catch (\Throwable $e) {
            Log::error('Evidence uploaded notification failed', ['error' => $e->getMessage(), 'request' => $refundRequest->id]);
        }
    }

    private function logAudit(RefundRequest $refundRequest, string $action, ?int $userId, ?string $details = null, ?array $metadata = null): void
    {
        try {
            ReturnAuditLog::create([
                'refund_request_id' => $refundRequest->id,
                'action'            => $action,
                'user_id'           => $userId,
                'details'           => $details,
                'metadata'          => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::error('Return audit log failed', ['error' => $e->getMessage(), 'request' => $refundRequest->id]);
        }
    }

    /**
     * Store an uploaded file directly in the public directory (bypasses storage symlink issues on nginx).
     *
     * @return string Relative path from public/ (e.g. "evidence/refunds/photo.jpg")
     */
    private function storeInPublic(UploadedFile $file, string $directory): string
    {
        $targetDir = public_path($directory);
        File::ensureDirectoryExists($targetDir);

        $filename = $file->hashName();
        $file->move($targetDir, $filename);

        return $directory . '/' . $filename;
    }
}
