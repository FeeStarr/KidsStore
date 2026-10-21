<?php

namespace App\Services;

use App\Exceptions\ProductionGateException;
use App\Models\CustomOrder;
use App\Models\CustomOrderMeasurement;
use App\Models\CustomOrderQcCheck;
use App\Models\CustomOrderStatusHistory;
use App\Notifications\CustomOrderCancelled;
use App\Notifications\CustomOrderCompleted;
use App\Notifications\CustomOrderInfoRequested;
use App\Notifications\CustomOrderInProduction;
use App\Notifications\CustomOrderReady;
use App\Notifications\CustomOrderReceived;
use App\Notifications\CustomOrderRejected;
use App\Notifications\CustomOrderReviewed;
use App\Notifications\CustomOrderShipped;
use App\Notifications\NotificationRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class CustomOrderService
{
    public const QC_CHECK_ITEMS = [
        'Measurements checked',
        'Correct fabric',
        'Correct colour',
        'Correct design',
        'Correct embellishments',
        'Stitching inspected',
        'Buttons/zips inspected',
        'No visible defects',
        'Final garment photographed',
    ];

    public function create(array $data, array $measurements, array $customizations): CustomOrder
    {
        return DB::transaction(function () use ($data, $measurements, $customizations) {
            $order = CustomOrder::create([
                'custom_order_number' => CustomOrder::generateReference(),
                'submission_token' => $data['submission_token'] ?? null,
                'user_id' => $data['user_id'],
                'item_type' => $data['item_type'] ?? 'frock',
                'category' => $data['category'] ?? null,
                'status' => CustomOrder::STATUS_DRAFT,
                'base_product_id' => $data['base_product_id'] ?? null,
                'child_name' => $data['child_name'] ?? null,
                'child_age' => $data['child_age'] ?? null,
                'child_gender' => $data['child_gender'] ?? null,
                'sizing_method' => $data['sizing_method'] ?? null,
                'size_chart_id' => $data['size_chart_id'] ?? null,
                'size_chart_version' => $data['size_chart_version'] ?? null,
                'selected_chart_size' => $data['selected_chart_size'] ?? null,
                'selected_chart_fit' => $data['selected_chart_fit'] ?? null,
                'custom_creation_id' => $data['custom_creation_id'] ?? null,
                'requires_measurement_confirmation' => (bool) ($data['requires_measurement_confirmation'] ?? false),
                'return_policy_acknowledged_at' => $data['return_policy_acknowledged_at'] ?? null,
                'delivery_method' => $data['delivery_method'] ?? 'delivery',
                'pickup_station_id' => $data['pickup_station_id'] ?? null,
                'delivery_location_id' => $data['delivery_location_id'] ?? null,
                'delivery_fee' => $data['delivery_fee'] ?? 0,
                'delivery_address' => $data['delivery_address'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
                'custom_colour_description' => $data['custom_colour_description'] ?? null,
                'return_policy_acknowledged' => $data['return_policy_acknowledged'] ?? false,
            ]);

            foreach ($measurements as $measurement) {
                $order->measurements()->create([
                    'measurement_type' => $measurement['type'],
                    'measurement_value' => $measurement['value'],
                    'measurement_unit' => $measurement['unit'] ?? 'cm',
                ]);
            }

            foreach ($customizations as $attribute => $value) {
                if (! empty($value)) {
                    $order->customizations()->create([
                        'attribute' => $attribute,
                        'value' => $value,
                    ]);
                }
            }

            return $order;
        });
    }

    public function findBySubmissionToken(?string $token, ?int $userId = null): ?CustomOrder
    {
        return CustomOrder::findBySubmissionToken($token, $userId);
    }

    public function submit(CustomOrder $order): CustomOrder
    {
        if ($order->status !== CustomOrder::STATUS_DRAFT) {
            throw new RuntimeException('Only draft orders can be submitted.');
        }

        $this->transitionTo($order, CustomOrder::STATUS_SUBMITTED);

        $order->update(['submitted_at' => now()]);

        // Notify customer
        $this->sendSafely($order, new CustomOrderReceived($order));

        // Notify admins
        $admins = NotificationRecipients::adminUsers();
        $this->sendToRecipientsSafely($order, $admins, new CustomOrderReceived($order));

        return $order->fresh();
    }

    public function review(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_UNDER_REVIEW, $userId);
        $this->sendSafely($order, new CustomOrderReviewed($order));

        return $order->fresh();
    }

    public function requestInfo(CustomOrder $order, string $message, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_NEEDS_INFORMATION, $userId);
        $this->sendSafely($order, new CustomOrderInfoRequested($order, $message));

        return $order->fresh();
    }

    public function approveForQuote(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_QUOTE_PENDING, $userId);

        return $order->fresh();
    }

    public function reject(CustomOrder $order, string $reason, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_REJECTED, $userId, $reason);

        $this->sendSafely($order, new CustomOrderRejected($order, $reason));

        return $order->fresh();
    }

    /**
     * A customer cancels their own request.
     *
     * Only before payment: after that the money is involved and the route is a
     * refund request, not a cancellation.
     */
    public function cancelAsCustomer(CustomOrder $order, string $reason, ?int $userId = null): CustomOrder
    {
        if (! $order->isCancellableByCustomer()) {
            throw new RuntimeException(
                'This request can no longer be cancelled. Once a frock has been paid for, '
                .'please raise a refund request instead.'
            );
        }

        return $this->performCancel($order, $reason, $userId);
    }

    /**
     * An administrator cancels a request.
     *
     * Wider than the customer's, because an administrator can scrap a frock that is
     * paid for or part way through being made.
     */
    public function cancelAsAdmin(CustomOrder $order, string $reason, ?int $userId = null): CustomOrder
    {
        if (! $order->isCancellableByAdmin()) {
            throw new RuntimeException(
                'This request can no longer be cancelled. A frock that has already shipped is returned, not cancelled.'
            );
        }

        return $this->performCancel($order, $reason, $userId);
    }

    /**
     * Cancels the request and the order that pays for it, together.
     *
     * The two records are one purchase. If they are allowed to drift apart, a
     * customer is left with a request that says "payment pending" and an invoice
     * that can no longer be paid.
     */
    private function performCancel(CustomOrder $order, string $reason, ?int $userId = null): CustomOrder
    {
        // The payable order goes first, so it can never be paid after the request
        // is gone. A paid order raises a refund request, which is how the money comes
        // back.
        $linked = $order->order;

        if ($linked) {
            app(OrderService::class)->cancelOrderOnly($linked, $reason);
        }

        $this->transitionTo($order, CustomOrder::STATUS_CANCELLED, $userId, $reason);

        // Notifications go out after the state has changed, so a mail failure cannot
        // undo the cancellation.
        $this->sendSafely($order, new CustomOrderCancelled($order, $reason));

        $admins = NotificationRecipients::adminUsers();
        $this->sendToRecipientsSafely($order, $admins, new CustomOrderCancelled($order, $reason));

        return $order->fresh();
    }

    public function transitionTo(CustomOrder $order, string $newStatus, ?int $userId = null, ?string $reason = null): void
    {
        if (! CustomOrder::canTransition($order->status, $newStatus)) {
            throw new RuntimeException(
                "Cannot transition from \"{$order->status}\" to \"{$newStatus}\"."
            );
        }

        $oldStatus = $order->status;
        $order->update(['status' => $newStatus]);

        $this->recordStatusChange($order, $oldStatus, $newStatus, $userId, $reason);

        $this->applyTimestamp($order, $newStatus);
    }

    public function recordStatusChange(CustomOrder $order, string $oldStatus, string $newStatus, ?int $userId, ?string $reason = null): void
    {
        CustomOrderStatusHistory::create([
            'custom_order_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $userId,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    public function applyTimestamp(CustomOrder $order, string $status): void
    {
        $column = match ($status) {
            CustomOrder::STATUS_QUOTED => 'quoted_at',
            CustomOrder::STATUS_CUSTOMER_APPROVED => 'approved_at',
            CustomOrder::STATUS_PAID => 'paid_at',
            CustomOrder::STATUS_IN_PRODUCTION => 'production_started_at',
            CustomOrder::STATUS_COMPLETED => 'completed_at',
            CustomOrder::STATUS_CANCELLED => 'cancelled_at',
            default => null,
        };

        if ($column) {
            $order->update([$column => now()]);
        }
    }

    public function startProduction(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        // Every blocker is evaluated before anything is written, so a refusal leaves
        // the status, the history and the notifications untouched.
        $blockers = $order->fresh()->productionGateBlockers();

        if ($blockers !== []) {
            throw new ProductionGateException($blockers);
        }

        $this->transitionTo($order, CustomOrder::STATUS_PRODUCTION_PENDING, $userId);
        $this->transitionTo($order->fresh(), CustomOrder::STATUS_IN_PRODUCTION, $userId);
        $this->sendSafely($order, new CustomOrderInProduction($order));

        return $order->fresh();
    }

    /**
     * Records the customer's agreement to the sizing basis, and freezes the evidence
     * they agreed to.
     *
     * The whole thing happens under a row lock, because two confirmations arriving
     * together must not be able to write two different snapshots: the first one to
     * take the lock wins, and the second finds the order already confirmed.
     *
     * @param  array<string, mixed>  $comparisonMeasurements
     */
    public function confirmSizing(
        CustomOrder $order,
        array $comparisonMeasurements = [],
        bool $reuseProductionAsComparison = false,
        ?int $userId = null
    ): CustomOrder {
        return DB::transaction(function () use ($order, $comparisonMeasurements, $reuseProductionAsComparison, $userId) {
            $locked = CustomOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->sizing_confirmed_at) {
                throw new RuntimeException('This sizing has already been confirmed and cannot be confirmed again.');
            }

            if ($locked->production_started_at) {
                throw new RuntimeException('This order is already in production, so its sizing can no longer be confirmed.');
            }

            $source = $this->recordComparisonMeasurements(
                $locked,
                $comparisonMeasurements,
                $reuseProductionAsComparison
            );

            $locked->sizing_confirmed_at = now();
            $locked->sizing_policy_acknowledged_at = now();

            // The snapshot is written after the confirmation timestamp is set, so it
            // records the confirmation itself. It refuses to overwrite an existing
            // one, which keeps the evidence frozen from here on.
            $locked->captureSizingSnapshot(null, $source, $userId);

            $locked->save();

            return $locked->fresh();
        });
    }

    /**
     * Stores the figures the customer supplied for comparison, and reports where
     * they came from.
     *
     * A customer who already sent their child's measurements for the order does not
     * get asked for them again: they are asked to confirm the existing figures are
     * still current, and only then are they copied into the comparison role. The
     * production figures are left exactly as they are, and the copy is never made
     * without that acknowledgement.
     *
     * @param  array<string, mixed>  $comparisonMeasurements
     */
    private function recordComparisonMeasurements(
        CustomOrder $order,
        array $comparisonMeasurements,
        bool $reuseProductionAsComparison
    ): ?string {
        $submitted = collect($comparisonMeasurements)
            ->map(fn ($row, $type) => [
                'type' => $type,
                'value' => $row['value'] ?? null,
                'unit' => $row['unit'] ?? 'cm',
            ])
            ->filter(fn ($row) => filled($row['value']))
            ->values()
            ->all();

        if ($submitted !== []) {
            foreach ($submitted as $row) {
                $order->comparisonMeasurements()->create([
                    'measurement_type' => $row['type'],
                    'measurement_value' => $row['value'],
                    'measurement_unit' => $row['unit'],
                    'role' => CustomOrderMeasurement::ROLE_COMPARISON,
                ]);
            }

            return 'submitted';
        }

        if ($reuseProductionAsComparison) {
            foreach ($order->measurements as $measurement) {
                $order->comparisonMeasurements()->create([
                    'measurement_type' => $measurement->measurement_type,
                    'measurement_value' => $measurement->measurement_value,
                    'measurement_unit' => $measurement->measurement_unit,
                    'role' => CustomOrderMeasurement::ROLE_COMPARISON,
                ]);
            }

            return 'reused_from_production';
        }

        return null;
    }

    public function submitForQc(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_QUALITY_CHECK, $userId);

        // Seed QC checklist items
        foreach (self::QC_CHECK_ITEMS as $item) {
            CustomOrderQcCheck::firstOrCreate(
                ['custom_order_id' => $order->id, 'check_item' => $item],
                ['checked_by' => $userId]
            );
        }

        return $order->fresh();
    }

    public function passQc(CustomOrder $order, ?string $deliveryMethod = null, ?int $userId = null): CustomOrder
    {
        $method = $deliveryMethod ?? $order->delivery_method;
        $targetStatus = ($method === 'pickup')
            ? CustomOrder::STATUS_READY_FOR_PICKUP
            : CustomOrder::STATUS_READY_FOR_DELIVERY;

        $this->transitionTo($order, $targetStatus, $userId);
        $this->sendSafely($order, new CustomOrderReady($order));

        return $order->fresh();
    }

    public function failQc(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_REWORK_REQUIRED, $userId);

        return $order->fresh();
    }

    public function resumeProduction(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_IN_PRODUCTION, $userId);

        return $order->fresh();
    }

    public function markReadyForPickup(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_READY_FOR_PICKUP, $userId);

        $this->sendSafely($order, new CustomOrderReady($order));

        return $order->fresh();
    }

    public function markReadyForDelivery(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_READY_FOR_DELIVERY, $userId);

        $this->sendSafely($order, new CustomOrderReady($order));

        return $order->fresh();
    }

    public function markShipped(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_SHIPPED, $userId);

        $this->sendSafely($order, new CustomOrderShipped($order));

        return $order->fresh();
    }

    public function complete(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_COMPLETED, $userId);
        $this->sendSafely($order, new CustomOrderCompleted($order));

        return $order->fresh();
    }

    public function getStatusLabel(string $status): string
    {
        return CustomOrder::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Dispatch a customer notification without letting a mail failure (SMTP timeout,
     * bad credentials) break the admin action that triggered it. The status change
     * has already been committed at this point and must stand on its own.
     *
     * A message that was saved, a quote that was sent, a cancellation that was
     * recorded - none of those stop being true because the email did not go out.
     */
    public function sendSafely(CustomOrder $order, $notification): void
    {
        try {
            $order->user->notify($notification);
        } catch (\Throwable $e) {
            Log::error('Custom order notification failed', [
                'error' => $e->getMessage(),
                'order' => $order->custom_order_number,
                'notification' => class_basename($notification),
                'email' => $order->user?->email,
            ]);
        }
    }

    /**
     * The same, for a group of recipients such as the admin team.
     */
    public function sendToRecipientsSafely(CustomOrder $order, $recipients, $notification): void
    {
        try {
            Notification::send($recipients, $notification);
        } catch (\Throwable $e) {
            Log::error('Custom order notification failed', [
                'error' => $e->getMessage(),
                'order' => $order->custom_order_number,
                'notification' => class_basename($notification),
                'recipients' => $recipients->count(),
            ]);
        }
    }
}
