<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupStation;
use App\Models\PickupPayout;
use App\Notifications\OrderItemPickedUpNotification;
use App\Notifications\OrderItemReceivedNotification;
use App\Notifications\OrderReadyForPickupNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PickupStationService
{
    /**
     * Mark an item as received at the station.
     */
    public function markReceived(OrderItem $item, ?int $stationId = null): OrderItem
    {
        if ($stationId && (int) $item->order->pickup_station_id !== $stationId) {
            throw new \RuntimeException('This item does not belong to your station.');
        }

        if ($item->pickup_status !== 'pending') {
            throw new \RuntimeException('Only pending items can be marked as received.');
        }

        // Order must be at least "shipping to station" before items can be marked received
        $validStatuses = [
            Order::STATUS_SHIPPING_TO_STATION,
            Order::STATUS_OUT_FOR_DELIVERY,
            Order::STATUS_READY_FOR_PICKUP,
        ];

        if (! in_array($item->order->status, $validStatuses)) {
            throw new \RuntimeException(
                'Order must be at "Shipping to Station" status before items can be marked as received.'
            );
        }

        $item->update([
            'pickup_status' => 'received',
            'pickup_status_changed_at' => now(),
        ]);

        // Notify customer that their item has been received at the station
        $this->notifyItemReceived($item);

        return $item->fresh();
    }

    /**
     * Mark an item as ready for customer pickup.
     */
    public function markReady(OrderItem $item, ?int $stationId = null): OrderItem
    {
        if ($stationId && (int) $item->order->pickup_station_id !== $stationId) {
            throw new \RuntimeException('This item does not belong to your station.');
        }

        if ($item->pickup_status !== 'received') {
            throw new \RuntimeException('Only received items can be marked as ready.');
        }

        $item->update([
            'pickup_status' => 'ready for pickup',
            'pickup_status_changed_at' => now(),
        ]);

        // Update order status to "ready for pick up" when all items are ready
        $order = $item->order;
        $allReady = $order->items()->where('pickup_status', '!=', 'ready for pickup')
            ->where('pickup_status', '!=', 'picked_up')->doesntExist();
        if ($allReady && $order->status !== 'ready for pick up' && $order->status !== 'delivered') {
            $order->update(['status' => 'ready for pick up']);
        }

        // Notify customer when their order items are ready for pickup
        $this->notifyReadyForPickup($order);

        return $item->fresh();
    }

    /**
     * Mark an item as picked up by the customer.
     * Requires payment to be confirmed first.
     */
    public function markPickedUp(OrderItem $item, ?int $stationId = null): OrderItem
    {
        if ($stationId && (int) $item->order->pickup_station_id !== $stationId) {
            throw new \RuntimeException('This item does not belong to your station.');
        }

        if ($item->pickup_status !== 'ready for pickup') {
            throw new \RuntimeException('Only items ready for pickup can be marked as picked up.');
        }

        // Check if order is paid
        if ($item->order->payment_status !== 'paid') {
            throw new \RuntimeException('Cannot confirm pickup — payment has not been received yet. Please collect payment first.');
        }

        DB::transaction(function () use ($item) {
            $item->update([
                'pickup_status' => 'picked_up',
                'pickup_status_changed_at' => now(),
            ]);

            // Update order's pickup_station_fee_total
            $this->refreshOrderFeeTotal($item->order);

            // Auto-deliver order when all items are picked up
            $order = $item->order;
            $allPickedUp = $order->items()->where('pickup_status', '!=', 'picked_up')->doesntExist();
            if ($allPickedUp && ! in_array($order->status, ['delivered', 'cancelled'])) {
                $order->update(['status' => 'delivered']);
            }
        });

        // Notify customer that their item has been picked up
        $this->notifyItemPickedUp($item);

        return $item->fresh();
    }

    /**
     * Notify the customer that an item has been picked up.
     */
    private function notifyItemPickedUp(OrderItem $item): void
    {
        try {
            // Prevent duplicate notifications within 60 seconds
            $cacheKey = "item_picked_up_notified_{$item->id}";
            if (cache()->has($cacheKey)) {
                return;
            }
            cache()->put($cacheKey, true, 60);

            $customer = $item->order->customer;
            if ($customer) {
                $customer->notify(new OrderItemPickedUpNotification($item->order, $item));
            }
        } catch (\Throwable $e) {
            Log::error('Item picked up notification failed', [
                'error'    => $e->getMessage(),
                'order'    => $item->order->reference,
                'item_id'  => $item->id,
            ]);
        }
    }

    /**
     * Refresh the order's pickup_station_fee_total based on picked-up items.
     * Commission is 10% of line_total (discounted price).
     */
    public function refreshOrderFeeTotal(Order $order): void
    {
        $raw = $order->items()
            ->where('pickup_status', 'picked_up')
            ->sum('line_total') * 0.10;

        // Apply per-order commission cap (min ₦500, max ₦2,000)
        $total = max(500.0, min(2000.0, $raw));

        $order->update(['pickup_station_fee_total' => round($total, 2)]);
    }

    /**
     * Get pending items for a station (not yet received).
     */
    public function getPendingItems(int $stationId)
    {
        return OrderItem::whereHas('order', function ($q) use ($stationId) {
            $q->where('pickup_station_id', $stationId)
              ->where('status', 'ready for pick up');
        })
        ->where('pickup_status', 'pending')
        ->with(['order', 'product', 'variant'])
        ->get();
    }

    /**
     * Get items at a station grouped by status.
     */
    public function getItemsByStatus(int $stationId): array
    {
        $items = OrderItem::whereHas('order', function ($q) use ($stationId) {
            $q->where('pickup_station_id', $stationId);
        })
        ->with(['order.customer', 'order.pickupStation', 'product', 'variant'])
        ->get();

        return [
            'pending' => $items->where('pickup_status', 'pending'),
            'received' => $items->where('pickup_status', 'received'),
            'ready' => $items->where('pickup_status', 'ready for pickup'),
            'picked_up' => $items->where('pickup_status', 'picked_up'),
        ];
    }

    /**
     * Calculate commission for picked-up items at a station.
     */
    public function calculateCommission(int $stationId): array
    {
        $items = OrderItem::whereHas('order', function ($q) use ($stationId) {
            $q->where('pickup_station_id', $stationId);
        })
        ->where('pickup_status', 'picked_up')
        ->with(['order', 'product', 'variant'])
        ->get();

        $totalCommission = 0;
        $itemsByOrder = [];

        foreach ($items as $item) {
            $orderId = $item->order_id;
            if (! isset($itemsByOrder[$orderId])) {
                $itemsByOrder[$orderId] = [
                    'order' => $item->order,
                    'items' => [],
                    'commission' => 0,
                ];
            }
            $itemsByOrder[$orderId]['items'][] = $item;
            $itemsByOrder[$orderId]['commission'] += $item->commission;
        }

        // Apply per-order commission cap (min ₦500, max ₦2,000)
        foreach ($itemsByOrder as &$entry) {
            $entry['commission'] = max(500.0, min(2000.0, $entry['commission']));
        }
        unset($entry);

        $totalCommission = array_sum(array_column($itemsByOrder, 'commission'));

        return [
            'total_commission' => round($totalCommission, 2),
            'items_by_order' => $itemsByOrder,
            'item_count' => $items->count(),
        ];
    }

    /**
     * Check for orders that have exceeded the 7-day collection window.
     * Returns orders where all items are ready for pickup but not collected after 7 days.
     */
    public function getExpiredOrders(int $stationId, int $days = 7): \Illuminate\Support\Collection
    {
        $cutoff = Carbon::now()->subDays($days);

        return Order::where('pickup_station_id', $stationId)
            ->where('status', 'ready for pick up')
            ->whereHas('items', function ($q) {
                $q->where('pickup_status', 'ready for pickup');
            })
            ->where('order_date', '<=', $cutoff)
            ->with(['items' => fn($q) => $q->where('pickup_status', 'ready for pickup')])
            ->get();
    }

    /**
     * Reassign an order to a different station.
     */
    public function reassignOrder(Order $order, int $newStationId, ?string $reason = null): Order
    {
        $newStation = PickupStation::findOrFail($newStationId);

        if (! $newStation->isOperational()) {
            throw new \RuntimeException('The selected station is not available.');
        }

        DB::transaction(function () use ($order, $newStationId, $reason) {
            // Reset all item pickup statuses
            $order->items()->update([
                'pickup_status' => 'pending',
                'pickup_status_changed_at' => now(),
                'pickup_station_fee_paid' => false,
                'pickup_station_fee_paid_at' => null,
            ]);

            $order->update([
                'pickup_station_id' => $newStationId,
                'pickup_station_fee_total' => 0,
            ]);

            // Log the reassignment (optional audit trail)
            if ($reason) {
                \App\Models\ReturnAuditLog::create([
                    'refund_request_id' => null,
                    'action' => 'station_reassigned',
                    'details' => "Order #{$order->reference} reassigned: {$reason}",
                    'user_id' => auth()->id(),
                ]);
            }
        });

        return $order->fresh();
    }

    /**
     * Toggle station availability.
     */
    public function setStationAvailability(PickupStation $station, bool $available, ?string $reason = null): PickupStation
    {
        $station->update([
            'is_available' => $available,
            'unavailability_reason' => $available ? null : $reason,
        ]);

        return $station->fresh();
    }

    /**
     * Notify customer that their order is ready for pickup.
     * Only sends once per order (deduplicates if multiple items become ready).
     */
    private function notifyReadyForPickup(Order $order): void
    {
        try {
            // Only notify if ALL items in the order are ready or already picked up
            $pendingItems = $order->items()
                ->whereIn('pickup_status', ['pending', 'received'])
                ->count();

            if ($pendingItems > 0) {
                return; // Not all items ready yet
            }

            // Prevent duplicate notifications within 60 seconds
            $cacheKey = "ready_pickup_notified_{$order->id}";
            if (cache()->has($cacheKey)) {
                return;
            }
            cache()->put($cacheKey, true, 60);

            $customer = $order->customer;
            if ($customer) {
                $customer->notify(new OrderReadyForPickupNotification($order));
            }
        } catch (\Throwable $e) {
            Log::error('Ready for pickup notification failed', [
                'error' => $e->getMessage(),
                'order' => $order->reference,
            ]);
        }
    }

    /**
     * Notify the customer that an item has been received at the station.
     */
    private function notifyItemReceived(OrderItem $item): void
    {
        try {
            // Prevent duplicate notifications within 60 seconds
            $cacheKey = "item_received_notified_{$item->id}";
            if (cache()->has($cacheKey)) {
                return;
            }
            cache()->put($cacheKey, true, 60);

            $customer = $item->order->customer;
            if ($customer) {
                $customer->notify(new OrderItemReceivedNotification($item->order, $item));
            }
        } catch (\Throwable $e) {
            Log::error('Item received notification failed', [
                'error'    => $e->getMessage(),
                'order'    => $item->order->reference,
                'item_id'  => $item->id,
            ]);
        }
    }

    /**
     * Get station payout summary (all picked up items with commission).
     */
    public function getPayoutSummary(int $stationId): array
    {
        $commission = $this->calculateCommission($stationId);

        $paidOut = PickupPayout::where('pickup_station_id', $stationId)
            ->where('is_reversed', false)
            ->sum('amount');

        return [
            'total_earned' => $commission['total_commission'],
            'total_paid_out' => round((float) $paidOut, 2),
            'balance_due' => round($commission['total_commission'] - (float) $paidOut, 2),
            'items' => $commission['items_by_order'],
            'item_count' => $commission['item_count'],
        ];
    }
}
