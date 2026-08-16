<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class CustomPaymentService
{
    public function createLinkedOrder(CustomOrder $order): Order
    {
        return DB::transaction(function () use ($order) {
            $sizeLabel = $this->buildSizeLabel($order);

            $linkedOrder = Order::create([
                'reference' => Order::generateReference(),
                'customer_id' => $order->user_id,
                'custom_order_id' => $order->id,
                'order_date' => now()->toDateString(),
                'status' => 'pending payment',
                'delivery_method' => $order->delivery_method,
                'payment_method' => 'paystack',
                'pickup_station_id' => $order->pickup_station_id,
                'delivery_address' => $order->delivery_address,
                'subtotal' => $order->total_amount,
                'grand_total' => $order->total_amount,
                'amount_paid' => 0,
                'note' => "Custom Order: {$order->custom_order_number}",
            ]);

            OrderItem::create([
                'order_id' => $linkedOrder->id,
                'product_id' => $order->base_product_id,
                'product_variant_id' => null,
                'quantity' => 1,
                'unit_price' => $order->total_amount,
                'original_unit_price' => $order->total_amount,
                'discount' => 0,
                'discount_amount' => 0,
                'line_total' => $order->total_amount,
                'selected_size' => $sizeLabel,
            ]);

            return $linkedOrder;
        });
    }

    private function buildSizeLabel(CustomOrder $order): string
    {
        $measurements = $order->measurements;
        if ($measurements->isEmpty()) {
            return $order->child_age ? "{$order->child_age} years (Standard)" : 'Standard Size';
        }

        $parts = $measurements->map(fn($m) => "{$m->measurement_type}: {$m->measurement_value}{$m->measurement_unit}");
        return 'Custom: ' . $parts->implode(', ');
    }
}
