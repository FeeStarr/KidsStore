<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Frock Request - {{ $customOrder->custom_order_number }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7;padding:20px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;">

    {{-- Header --}}
    <tr>
        <td style="background-color:#2563eb;padding:24px 30px;text-align:center;">
            <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600;">{{ ($isInternal ?? false) ? 'New Custom Frock Request' : 'Custom Frock Request Received' }}</h1>
            <p style="margin:6px 0 0;color:#bfdbfe;font-size:14px;">{{ $customOrder->custom_order_number }}</p>
        </td>
    </tr>

    {{-- Greeting --}}
    <tr>
        <td style="padding:28px 30px 0;">
            @if($isInternal ?? false)
                <p style="margin:0;font-size:16px;color:#1f2937;">Hello {{ $notifiable->name }},</p>
                <p style="margin:10px 0 0;font-size:14px;color:#4b5563;line-height:1.6;">
                    A new custom frock request <strong>{{ $customOrder->custom_order_number }}</strong> has been submitted
                    by {{ $customOrder->user?->name ?? 'a customer' }}@if($customOrder->child_name) for <strong>{{ $customOrder->child_name }}</strong>@endif.
                    Please review it and prepare a quote.
                </p>
            @else
                <p style="margin:0;font-size:16px;color:#1f2937;">Hello {{ $notifiable->name }}!</p>
                <p style="margin:10px 0 0;font-size:14px;color:#4b5563;line-height:1.6;">
                    We've received your custom frock request. Our team will review your request and get back to you shortly.
                </p>
            @endif
        </td>
    </tr>

    {{-- Reference design --}}
    @if($thumbnailCid)
    <tr>
        <td style="padding:24px 30px 0;">
            <h2 style="margin:0 0 12px;font-size:15px;color:#1f2937;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Reference Design</h2>
            <table cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;">
                <tr>
                    <td style="padding:0;line-height:0;font-size:0;">
                        <img src="{{ $thumbnailCid }}" alt="Custom frock reference design" width="320" style="width:320px;max-width:100%;height:auto;display:block;border:0;">
                    </td>
                </tr>
            </table>
            <p style="margin:8px 0 0;font-size:12px;color:#6b7280;line-height:1.6;">
                @if($isInternal ?? false)
                    The design submitted by the customer. The final garment may differ slightly
                    due to fabric availability and production methods - please confirm with the
                    customer before starting production.
                @else
                    The design you uploaded. This is a preview only - the final garment may differ
                    slightly due to fabric availability and production methods.
                @endif
            </p>
        </td>
    </tr>
    @endif

    {{-- CTA --}}
    <tr>
        <td style="padding:24px 30px;text-align:center;">
            @if($isInternal ?? false)
                <a href="{{ route('admin.custom-orders.show', $customOrder) }}" style="display:inline-block;background-color:#2563eb;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">View Order in Admin</a>
            @else
                <a href="{{ route('shop.custom-frock.show', $customOrder) }}" style="display:inline-block;background-color:#2563eb;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">View Your Request</a>
            @endif
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="padding:0 30px 24px;">
            <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;text-align:center;">
                @if($isInternal ?? false)
                    Please review and respond to the customer promptly.
                @else
                    Thank you for choosing KidsFlairr!
                @endif
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
