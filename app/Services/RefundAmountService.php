<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundRequest;
use App\Models\Setting;

/**
 * Explicit refund amount calculation per corrected spec.
 * Handles whole-order and item-level apportionment of discounts/coupons/fees.
 */
class RefundAmountService
{
    /**
     * Whole-order cancellation refund.
     */
    public function forWholeOrder(Order $order): float
    {
        $paid = (float) $order->amount_paid;
        if ($paid <= 0) {
            return 0.0;
        }

        // If fully paid, refund full paid amount minus any already refunded.
        $alreadyRefunded = $this->alreadyRefundedAmount($order);
        $refundable = max(0, $paid - $alreadyRefunded);

        // For unpaid pending, no refund.
        return round($refundable, 2);
    }

    /**
     * Item-level cancellation refund with apportionment.
     */
    public function forItem(Order $order, OrderItem $item, int $qty): float
    {
        $qty = max(1, $qty);
        $remaining = $item->quantity - (int) $item->cancelled_quantity;
        $qty = min($qty, $remaining);
        if ($qty <= 0) {
            return 0.0;
        }

        // Base net price per unit
        $unitNet = (float) $item->unit_price * (1 - (float) $item->discount / 100);
        $itemNet = round($unitNet * $qty, 2);

        // Apportion order-level discount proportionally
        $orderSubtotal = (float) $order->items()->sum('line_total');
        $share = $orderSubtotal > 0 ? ((float) $item->line_total / $orderSubtotal) : 0;
        // But for partial qty, scale share by qty fraction
        $qtyFraction = $item->quantity > 0 ? $qty / (int) $item->quantity : 1;
        $apportionedShare = $share * $qtyFraction;

        $orderDiscount = (float) $order->discount; // percentage
        // Order discount is percentage, apportioned not needed separately as line_total already net, but grand_total includes it.
        // For item-level, we need to deduct proportional order discount + coupon discount.
        $totalCoupon = (float) $order->items()->sum('coupon_discount');
        $couponShare = $totalCoupon * $apportionedShare;

        // Shipping apportionment: only if reason qualifies and full order not yet refunded.
        // Caller decides shipping inclusion; here we return net + coupon share, shipping added by caller if needed.
        $refund = $itemNet - $couponShare;

        // Do not exceed amount_paid pro-rata.
        $alreadyRefunded = $this->alreadyRefundedAmount($order);
        $maxRefund = max(0, (float) $order->amount_paid - $alreadyRefunded);
        $refund = min($refund, $maxRefund);

        return round(max(0, $refund), 2);
    }

    /**
     * Add shipping refund if applicable for item reason.
     */
    public function withShipping(Order $order, float $amount, string $reason): float
    {
        if (! in_array($reason, RefundRequest::SHIPPING_REFUND_REASONS, true)) {
            return $amount;
        }
        $shippingBefore = (float) $order->shipping_fee;
        $discountPct = (float) Setting::get('shipping_discount', 0);
        $shippingNet = $shippingBefore * (1 - $discountPct / 100);
        return round($amount + $shippingNet, 2);
    }

    private function alreadyRefundedAmount(Order $order): float
    {
        return (float) $order->refundRequests()
            ->whereIn('status', [RefundRequest::STATUS_REFUNDED, RefundRequest::STATUS_REFUND_PROCESSING])
            ->sum('amount');
    }
}
