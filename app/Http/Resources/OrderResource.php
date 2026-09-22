<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'reference'         => $this->reference,
            'status'            => $this->status,
            'status_label'      => $this->getStatusLabel(),
            'delivery_method'   => $this->delivery_method,
            'payment_method'    => $this->payment_method,
            'payment_status'    => $this->payment_status,
            'guest_name'        => $this->guest_name,
            'guest_email'       => $this->guest_email,
            'guest_phone'       => $this->guest_phone,
            'subtotal'          => (float) $this->subtotal,
            'discount'          => (float) $this->discount,
            'shipping_fee'      => (float) $this->shipping_fee,
            'grand_total'       => (float) $this->grand_total,
            'amount_paid'       => (float) $this->amount_paid,
            'balance'           => (float) $this->balance,
            'note'              => $this->note,
            'delivery_address'  => $this->delivery_address,
            'tracking_number'   => $this->tracking_number,
            'tracking_url'      => $this->tracking_url,
            'courier_name'      => $this->courier_name,
            'order_date'        => $this->order_date?->toISOString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toISOString(),
            'ordered_at'        => $this->ordered_at?->toISOString(),
            'confirmed_at'      => $this->confirmed_at?->toISOString(),
            'processing_at'     => $this->processing_at?->toISOString(),
            'shipped_at'        => $this->shipped_at?->toISOString(),
            'delivered_at'      => $this->delivered_at?->toISOString(),
            'cancelled_at'      => $this->cancelled_at?->toISOString(),
            'delivery_window'   => $this->delivery_window,
            'items'             => OrderItemResource::collection($this->whenLoaded('items')),
            'pickup_station'    => new PickupStationResource($this->whenLoaded('pickupStation')),
            'delivery_location' => new DeliveryLocationResource($this->whenLoaded('deliveryLocation')),
        ];
    }
}
