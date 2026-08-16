<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\CustomOrderQcCheck;
use App\Models\CustomOrderStatusHistory;
use App\Models\User;
use App\Notifications\CustomOrderCompleted;
use App\Notifications\CustomOrderInProduction;
use App\Notifications\CustomOrderReady;
use App\Notifications\CustomOrderReceived;
use App\Notifications\CustomOrderReviewed;
use App\Notifications\CustomOrderInfoRequested;
use App\Notifications\CustomOrderCancelled;
use Illuminate\Support\Facades\DB;
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
                'user_id' => $data['user_id'],
                'item_type' => $data['item_type'] ?? 'frock',
                'status' => CustomOrder::STATUS_DRAFT,
                'base_product_id' => $data['base_product_id'] ?? null,
                'child_name' => $data['child_name'] ?? null,
                'child_age' => $data['child_age'] ?? null,
                'child_gender' => $data['child_gender'] ?? null,
                'delivery_method' => $data['delivery_method'] ?? 'delivery',
                'pickup_station_id' => $data['pickup_station_id'] ?? null,
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
                if (!empty($value)) {
                    $order->customizations()->create([
                        'attribute' => $attribute,
                        'value' => $value,
                    ]);
                }
            }

            return $order;
        });
    }

    public function submit(CustomOrder $order): CustomOrder
    {
        if ($order->status !== CustomOrder::STATUS_DRAFT) {
            throw new RuntimeException('Only draft orders can be submitted.');
        }

        $this->transitionTo($order, CustomOrder::STATUS_SUBMITTED);

        $order->update(['submitted_at' => now()]);

        // Notify customer
        $order->user->notify(new CustomOrderReceived($order));

        // Notify admins
        $admins = User::role(['superadmin', 'admin'])->get();
        Notification::send($admins, new CustomOrderReceived($order));

        return $order->fresh();
    }

    public function review(CustomOrder $order): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_UNDER_REVIEW);
        $order->user->notify(new CustomOrderReviewed($order));
        return $order->fresh();
    }

    public function requestInfo(CustomOrder $order, string $message): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_NEEDS_INFORMATION);
        $order->user->notify(new CustomOrderInfoRequested($order, $message));
        return $order->fresh();
    }

    public function approveForQuote(CustomOrder $order): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_QUOTE_PENDING);
        return $order->fresh();
    }

    public function reject(CustomOrder $order, string $reason): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_REJECTED, null, $reason);
        return $order->fresh();
    }

    public function cancel(CustomOrder $order): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_CANCELLED);

        // Notify customer
        $order->user->notify(new CustomOrderCancelled($order));

        // Notify admins
        $admins = User::role(['superadmin', 'admin'])->get();
        Notification::send($admins, new CustomOrderCancelled($order));

        return $order->fresh();
    }

    public function transitionTo(CustomOrder $order, string $newStatus, ?int $userId = null, ?string $reason = null): void
    {
        if (!CustomOrder::canTransition($order->status, $newStatus)) {
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
        $this->transitionTo($order, CustomOrder::STATUS_PRODUCTION_PENDING, $userId);
        $this->transitionTo($order->fresh(), CustomOrder::STATUS_IN_PRODUCTION, $userId);
        $order->user->notify(new CustomOrderInProduction($order));
        return $order->fresh();
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
        $order->user->notify(new CustomOrderReady($order));
        return $order->fresh();
    }

    public function failQc(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_REWORK_REQUIRED, $userId);
        return $order->fresh();
    }

    public function markReadyForPickup(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_READY_FOR_PICKUP, $userId);
        return $order->fresh();
    }

    public function markShipped(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_SHIPPED, $userId);
        return $order->fresh();
    }

    public function complete(CustomOrder $order, ?int $userId = null): CustomOrder
    {
        $this->transitionTo($order, CustomOrder::STATUS_COMPLETED, $userId);
        $order->user->notify(new CustomOrderCompleted($order));
        return $order->fresh();
    }

    public function getStatusLabel(string $status): string
    {
        return CustomOrder::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
