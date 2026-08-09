@php
    $rr     = $refundRequest;
    $scope  = $rr->getScopeLabel();
    $amount = '₦' . number_format($rr->amount, 2);

    // Build exchange info if applicable
    $exchangeLabel = $rr->exchangeVariant ? $rr->exchangeVariant->options_label : null;

    $intro = match ($rr->status) {
        \App\Models\RefundRequest::STATUS_REQUESTED          => "We've received your return request. Your request for <strong>{$scope}</strong> is now under review. We'll get back to you within <strong>1–6 business days</strong>.",
        \App\Models\RefundRequest::STATUS_AWAITING_EVIDENCE => "We need additional evidence to process your return. Please upload the required photos/videos for <strong>{$scope}</strong> to continue.",
        \App\Models\RefundRequest::STATUS_APPROVED          => "Your return request has been <strong>approved</strong>. Please ship the item back to us. Once we receive and inspect it, your <strong>{$amount}</strong> refund will be processed within 5–7 working days.",
        \App\Models\RefundRequest::STATUS_REJECTED          => "Unfortunately, your return request has been <strong>declined</strong>.",
        \App\Models\RefundRequest::STATUS_RECEIVED          => "We've received your returned item. Our team is inspecting <strong>{$scope}</strong>. You'll receive an update once the inspection is complete.",
        \App\Models\RefundRequest::STATUS_REFUND_APPROVED,
        \App\Models\RefundRequest::STATUS_REFUND_PROCESSING => "Your refund has been approved and is being processed. <strong>{$amount}</strong> for <strong>{$scope}</strong> will be credited to your original payment method within 5–7 working days.",
        \App\Models\RefundRequest::STATUS_REFUNDED          => "Your refund has been <strong>processed successfully</strong>. <strong>{$amount}</strong> for <strong>{$scope}</strong> has been returned to your original payment method. It may take 1–3 business days to appear.",
        \App\Models\RefundRequest::STATUS_REPLACEMENT_APPROVED => $exchangeLabel
            ? "Your exchange request has been <strong>approved</strong>. A replacement (<strong>{$exchangeLabel}</strong>) for <strong>{$scope}</strong> will be shipped to you shortly."
            : "Your replacement request has been <strong>approved</strong>. A replacement for <strong>{$scope}</strong> will be shipped to you shortly.",
        \App\Models\RefundRequest::STATUS_REPLACEMENT_SHIPPED => $exchangeLabel
            ? "Your replacement has been <strong>shipped</strong>. Your exchange item (<strong>{$exchangeLabel}</strong>) for <strong>{$scope}</strong> is on its way."
            : "Your replacement has been <strong>shipped</strong>. Your replacement for <strong>{$scope}</strong> is on its way.",
        \App\Models\RefundRequest::STATUS_CANCELLED         => "Your return request has been <strong>cancelled</strong>. If this was a mistake, you can submit a new request within the return window.",
        \App\Models\RefundRequest::STATUS_RETURN_COLLECTED  => "The returned item for <strong>{$scope}</strong> has been <strong>collected at the pickup station</strong>. Our team will inspect the item and process your <strong>{$amount}</strong> refund shortly.",
        default => "Your return request has been updated. Status: <strong>{$rr->status_label}</strong>",
    };

    $headerColor = match ($rr->status) {
        \App\Models\RefundRequest::STATUS_APPROVED,
        \App\Models\RefundRequest::STATUS_REFUND_APPROVED,
        \App\Models\RefundRequest::STATUS_REFUND_PROCESSING,
        \App\Models\RefundRequest::STATUS_REFUNDED,
        \App\Models\RefundRequest::STATUS_REPLACEMENT_APPROVED,
        \App\Models\RefundRequest::STATUS_REPLACEMENT_SHIPPED => '#16a34a',
        \App\Models\RefundRequest::STATUS_REJECTED,
        \App\Models\RefundRequest::STATUS_CANCELLED,
        \App\Models\RefundRequest::STATUS_REFUND_FAILED => '#dc2626',
        default => '#2563eb',
    };

    $headerLabel = match ($rr->status) {
        \App\Models\RefundRequest::STATUS_REQUESTED          => 'Return Request Received',
        \App\Models\RefundRequest::STATUS_AWAITING_EVIDENCE => 'Evidence Needed',
        \App\Models\RefundRequest::STATUS_APPROVED          => 'Return Approved',
        \App\Models\RefundRequest::STATUS_REJECTED          => 'Return Declined',
        \App\Models\RefundRequest::STATUS_RECEIVED          => 'Item Received',
        \App\Models\RefundRequest::STATUS_REFUND_APPROVED,
        \App\Models\RefundRequest::STATUS_REFUND_PROCESSING => 'Refund Approved',
        \App\Models\RefundRequest::STATUS_REFUNDED          => 'Refund Completed',
        \App\Models\RefundRequest::STATUS_REPLACEMENT_APPROVED => 'Replacement Approved',
        \App\Models\RefundRequest::STATUS_REPLACEMENT_SHIPPED  => 'Replacement Shipped',
        \App\Models\RefundRequest::STATUS_CANCELLED         => 'Return Cancelled',
        \App\Models\RefundRequest::STATUS_RETURN_COLLECTED  => 'Return Item Collected',
        default => 'Return Update',
    };

    $thumb = '';
    if ($rr->orderItem) {
        $item = $rr->orderItem;
        if ($item->variant && $item->variant->image) {
            $thumb = $item->variant->image->url;
        } elseif ($item->product && $item->product->catalog_image) {
            $thumb = $item->product->catalog_image;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $headerLabel }} — {{ $order->reference }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7;padding:20px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;">

    {{-- Header --}}
    <tr>
        <td style="background-color:{{ $headerColor }};padding:24px 30px;text-align:center;">
            <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600;">{{ $headerLabel }}</h1>
            <p style="margin:6px 0 0;color:{{ $headerColor === '#dc2626' ? '#fecaca' : ($headerColor === '#16a34a' ? '#bbf7d0' : '#bfdbfe') }};font-size:14px;">{{ $order->reference }}</p>
        </td>
    </tr>

    {{-- Greeting --}}
    <tr>
        <td style="padding:28px 30px 0;">
            <p style="margin:0;font-size:16px;color:#1f2937;">Hello {{ $notifiable->name }},</p>
            <p style="margin:10px 0 0;font-size:14px;color:#4b5563;line-height:1.6;">{!! $intro !!}</p>
        </td>
    </tr>

    @if($rr->status === \App\Models\RefundRequest::STATUS_REJECTED && $rr->admin_note)
    {{-- Rejection Reason --}}
    <tr>
        <td style="padding:16px 30px 0;">
            <div style="background-color:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:14px 18px;">
                <p style="margin:0;font-size:13px;color:#991b1b;">
                    <strong>Reason:</strong> {{ $rr->admin_note }}
                </p>
            </div>
        </td>
    </tr>
    @endif

    {{-- Item Card --}}
    <tr>
        <td style="padding:24px 30px 0;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;">
                <tr>
                    <td style="padding:16px 20px;vertical-align:top;width:64px;">
                        @if($thumb)
                            <img src="{{ $thumb }}" alt="{{ $rr->orderItem?->product?->name ?? 'Product' }}" width="56" height="56" style="width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;">
                        @else
                            <div style="width:56px;height:56px;background:#e5e7eb;border-radius:6px;text-align:center;line-height:56px;color:#9ca3af;font-size:11px;">No img</div>
                        @endif
                    </td>
                    <td style="padding:16px 12px 16px 0;vertical-align:top;">
                        <div style="font-size:14px;color:#1f2937;font-weight:600;margin:0;">{{ $rr->orderItem?->product?->name ?? 'Product' }}</div>
                        @if($rr->orderItem?->variant?->options_label)
                            <div style="font-size:12px;color:#6b7280;margin:3px 0 0;">{{ $rr->orderItem->variant->options_label }}</div>
                        @endif
                        <div style="font-size:12px;color:#6b7280;margin:2px 0 0;">Qty: {{ $rr->quantity }}</div>
                    </td>
                    <td style="padding:16px 20px 16px 0;vertical-align:top;text-align:right;white-space:nowrap;">
                        <div style="font-size:14px;color:#1f2937;font-weight:700;">{{ $amount }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Return Details --}}
    <tr>
        <td style="padding:20px 30px 0;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding:6px 0;font-size:13px;color:#6b7280;">Order Reference</td>
                    <td style="padding:6px 0;font-size:13px;color:#1f2937;text-align:right;font-weight:600;">{{ $order->reference }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;font-size:13px;color:#6b7280;">Return Reason</td>
                    <td style="padding:6px 0;font-size:13px;color:#1f2937;text-align:right;font-weight:600;">{{ $rr->reason_label }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;font-size:13px;color:#6b7280;">Status</td>
                    <td style="padding:6px 0;font-size:13px;color:#1f2937;text-align:right;font-weight:600;">{{ $rr->status_label }}</td>
                </tr>
                @if($exchangeLabel && in_array($rr->status, [
                    \App\Models\RefundRequest::STATUS_REPLACEMENT_APPROVED,
                    \App\Models\RefundRequest::STATUS_REPLACEMENT_SHIPPED,
                ]))
                <tr>
                    <td style="padding:6px 0;font-size:13px;color:#6b7280;">Replacement Variant</td>
                    <td style="padding:6px 0;font-size:13px;color:#1f2937;text-align:right;font-weight:600;">{{ $exchangeLabel }}</td>
                </tr>
                @endif
            </table>
        </td>
    </tr>

    {{-- CTA --}}
    <tr>
        <td style="padding:24px 30px;text-align:center;">
            <a href="{{ url('/account/orders/' . $order->id) }}" style="display:inline-block;background-color:{{ $headerColor }};color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">View Your Order</a>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="padding:0 30px 24px;">
            <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;text-align:center;">
                If you have questions, please contact our support team.
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
