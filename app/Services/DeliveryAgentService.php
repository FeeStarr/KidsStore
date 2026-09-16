<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DeliveryAgent;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DeliveryAgentService
{
    public function getDashboardStats(int $agentId): array
    {
        $base = Order::where('delivery_agent_id', $agentId);

        return [
            'assigned'         => (clone $base)->where('delivery_status', 'assigned')->count(),
            'received'         => (clone $base)->where('delivery_status', 'received')->count(),
            'out_for_delivery' => (clone $base)->where('delivery_status', 'out_for_delivery')->count(),
            'delivered'        => (clone $base)->where('delivery_status', 'delivered')->count(),
            'failed'           => (clone $base)->where('delivery_status', 'failed')->count(),
        ];
    }

    public function getDeliveries(int $agentId, ?string $filter = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Order::where('delivery_agent_id', $agentId)
            ->whereIn('delivery_status', ['assigned', 'received', 'out_for_delivery', 'delivered', 'failed'])
            ->with('customer', 'deliveryLocation');

        if ($filter && $filter !== 'all') {
            $query->where('delivery_status', $filter);
        }

        return $query->latest()->get();
    }

    public function markReceived(Order $order, DeliveryAgent $agent): Order
    {
        $this->assertOwnership($order, $agent);
        abort_unless($order->delivery_status === Order::DELIVERY_STATUS_ASSIGNED, 400, 'Invalid status for this action.');

        $order->update([
            'delivery_status'      => Order::DELIVERY_STATUS_RECEIVED,
            'delivery_received_at' => now(),
        ]);

        $this->logAction('delivery.received', $order, $agent);

        return $order->fresh();
    }

    public function markOutForDelivery(Order $order, DeliveryAgent $agent): Order
    {
        $this->assertOwnership($order, $agent);
        abort_unless($order->delivery_status === Order::DELIVERY_STATUS_RECEIVED, 400, 'Invalid status for this action.');

        $order->update([
            'delivery_status'              => Order::DELIVERY_STATUS_OUT_FOR_DELIVERY,
            'delivery_out_for_delivery_at' => now(),
        ]);

        // Update overall order status + notify customer
        if (in_array($order->status, ['ordered', 'pending confirmation', 'confirmed', 'processing'], true)) {
            app(OrderService::class)->markShipped($order);
        }

        $this->logAction('delivery.out_for_delivery', $order, $agent);

        return $order->fresh();
    }

    public function markDelivered(Order $order, DeliveryAgent $agent): Order
    {
        $this->assertOwnership($order, $agent);
        abort_unless(
            in_array($order->delivery_status, [
                Order::DELIVERY_STATUS_RECEIVED,
                Order::DELIVERY_STATUS_OUT_FOR_DELIVERY,
            ]),
            400,
            'Invalid status for this action.'
        );

        abort_unless(
            in_array($order->payment_status, ['paid', 'partial']),
            400,
            'Cannot mark as delivered: payment has not been confirmed.'
        );

        $order->update([
            'delivery_status'       => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_delivered_at' => now(),
        ]);

        // Update overall order status if in a valid pre-delivery state
        if (in_array($order->status, ['out for delivery', 'processing'], true)) {
            app(OrderService::class)->markDelivered($order);
        }

        $this->logAction('delivery.delivered', $order, $agent);

        return $order->fresh();
    }

    public function reportIssue(Order $order, DeliveryAgent $agent, string $reason, ?string $notes): Order
    {
        $this->assertOwnership($order, $agent);
        abort_unless(
            in_array($order->delivery_status, [
                Order::DELIVERY_STATUS_ASSIGNED,
                Order::DELIVERY_STATUS_RECEIVED,
                Order::DELIVERY_STATUS_OUT_FOR_DELIVERY,
            ]),
            400,
            'Invalid status for this action.'
        );

        $order->update([
            'delivery_status'       => Order::DELIVERY_STATUS_FAILED,
            'delivery_issue_reason' => $reason,
            'delivery_issue_notes'  => $notes,
        ]);

        $this->logAction('delivery.issue_reported', $order, $agent, [
            'reason' => $reason,
            'notes'  => $notes,
        ]);

        return $order->fresh();
    }

    private function assertOwnership(Order $order, DeliveryAgent $agent): void
    {
        abort_unless((int) $order->delivery_agent_id === (int) $agent->id, 403, 'This delivery does not belong to you.');
    }

    private function logAction(string $action, Order $order, DeliveryAgent $agent, ?array $extra = null): void
    {
        try {
            AuditLog::create([
                'user_id'        => Auth::id(),
                'action'         => $action,
                'auditable_type' => Order::class,
                'auditable_id'   => $order->id,
                'meta'           => json_encode(array_merge([
                    'delivery_agent_id' => $agent->id,
                    'account_number'    => $agent->account_number,
                ], $extra ?? [])),
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Delivery audit log failed', ['error' => $e->getMessage()]);
        }
    }
}
