<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - {{ $order->reference }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7;padding:20px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;">

    {{-- Header --}}
    <tr>
        <td style="background-color:#2563eb;padding:24px 30px;text-align:center;">
            <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600;">{{ ($isInternal ?? false) ? 'New Order Placed' : 'Order Confirmed' }}</h1>
            <p style="margin:6px 0 0;color:#bfdbfe;font-size:14px;">{{ $order->reference }}</p>
        </td>
    </tr>

    {{-- Greeting --}}
    <tr>
        <td style="padding:28px 30px 0;">
            @if($isInternal ?? false)
                <p style="margin:0;font-size:16px;color:#1f2937;">New order from {{ $order->customer?->name ?? 'a customer' }}</p>
                <p style="margin:10px 0 0;font-size:14px;color:#4b5563;line-height:1.6;">
                    A new order <strong>{{ $order->reference }}</strong> has been placed. Please review and process.
                </p>
            @else
                <p style="margin:0;font-size:16px;color:#1f2937;">Hello {{ $order->customer?->name ?? 'there' }},</p>
                <p style="margin:10px 0 0;font-size:14px;color:#4b5563;line-height:1.6;">
                    Thank you for your order! We've received it and will begin processing shortly.
                </p>
            @endif
        </td>
    </tr>

    {{-- Order Summary --}}
    <tr>
        <td style="padding:20px 30px 0;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;">
                <tr>
                    <td style="padding:16px 20px;border-bottom:1px solid #e5e7eb;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-size:13px;color:#6b7280;">Order Date</td>
                                <td style="font-size:13px;color:#1f2937;text-align:right;font-weight:600;">{{ $order->order_date->format('M d, Y') }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 20px;border-bottom:1px solid #e5e7eb;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-size:13px;color:#6b7280;">Delivery Method</td>
                                <td style="font-size:13px;color:#1f2937;text-align:right;font-weight:600;">{{ $order->getDeliveryMethodLabel() }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @if($order->isForPickup() && $order->pickupStation)
                <tr>
                    <td style="padding:12px 20px;border-bottom:1px solid #e5e7eb;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-size:13px;color:#6b7280;">Pickup Station</td>
                                <td style="font-size:13px;color:#1f2937;text-align:right;font-weight:600;">{{ $order->pickupStation->name }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @endif
                <tr>
                    <td style="padding:12px 20px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-size:14px;color:#1f2937;font-weight:700;">Total</td>
                                <td style="font-size:14px;color:#2563eb;text-align:right;font-weight:700;">&#8358;{{ number_format($order->grand_total, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Items --}}
    <tr>
        <td style="padding:24px 30px 0;">
            <h2 style="margin:0 0 14px;font-size:15px;color:#1f2937;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Items Ordered</h2>
            <table width="100%" cellpadding="0" cellspacing="0">
                @foreach($order->items as $item)
                <tr>
                    <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;vertical-align:top;width:64px;">
                        @php
                            $thumb = '';
                            if ($item->variant && $item->variant->image) {
                                $thumb = $item->variant->image->url;
                            } elseif ($item->product && $item->product->catalog_image) {
                                $thumb = $item->product->catalog_image;
                            }
                        @endphp
                        @if($thumb)
                            <img src="{{ $thumb }}" alt="{{ $item->product?->name }}" width="56" height="56" style="width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;">
                        @else
                            <div style="width:56px;height:56px;background:#e5e7eb;border-radius:6px;text-align:center;line-height:56px;color:#9ca3af;font-size:11px;">No img</div>
                        @endif
                    </td>
                    <td style="padding:10px 0 10px 12px;border-bottom:1px solid #f3f4f6;vertical-align:top;">
                        <div style="font-size:14px;color:#1f2937;font-weight:600;margin:0;">{{ $item->product?->name ?? 'Product' }}</div>
                        @if($item->variant?->options_label)
                            <div style="font-size:12px;color:#6b7280;margin:3px 0 0;">{{ $item->variant->options_label }}</div>
                        @endif
                        @if($item->selected_age_group)
                            <div style="font-size:12px;color:#6b7280;margin:2px 0 0;">Age: {{ $item->selected_age_group }}</div>
                        @endif
                    </td>
                    <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;vertical-align:top;text-align:right;white-space:nowrap;">
                        <div style="font-size:13px;color:#4b5563;">{{ $item->quantity }} × &#8358;{{ number_format($item->unit_price, 2) }}</div>
                        <div style="font-size:14px;color:#1f2937;font-weight:600;margin-top:3px;">&#8358;{{ number_format($item->line_total, 2) }}</div>
                    </td>
                </tr>
                @endforeach
            </table>
        </td>
    </tr>

    {{-- Delivery Estimate --}}
    @if($order->status !== 'cancelled')
    <tr>
        <td style="padding:20px 30px 0;">
            <div style="background-color:#ecfdf5;border:1px solid #a7f3d0;border-radius:6px;padding:14px 18px;">
                <p style="margin:0;font-size:13px;color:#065f46;">
                    <strong>Estimated Delivery:</strong> {{ $order->delivery_window }}
                </p>
            </div>
        </td>
    </tr>
    @endif

    {{-- CTA --}}
    <tr>
        <td style="padding:24px 30px;text-align:center;">
            @if($isInternal ?? false)
                <a href="{{ url('/admin/orders/' . $order->id) }}" style="display:inline-block;background-color:#2563eb;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">View Order in Admin</a>
            @else
                <a href="{{ url('/account/orders/' . $order->id) }}" style="display:inline-block;background-color:#2563eb;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">View Your Order</a>
            @endif
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="padding:0 30px 24px;">
            <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;text-align:center;">
                If you have any questions, please contact our support team.
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
