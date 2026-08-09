@php
    $rr = $refundRequest;
@endphp

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:system-ui,-apple-system,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;padding:30px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:{{ $headerColor }};padding:24px 30px;text-align:center;">
                            <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:700;">{{ $headerLabel }}</h1>
                            <p style="margin:6px 0 0;color:rgba(255,255,255,0.9);font-size:13px;">Return #{{ $rr->id }} · {{ $order->reference }}</p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 16px;font-size:15px;color:#1f2937;line-height:1.6;">{!! $intro !!}</p>

                            {{-- Details Table --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;">
                                <tr>
                                    <td style="padding:10px 16px;background-color:#f9fafb;font-size:13px;color:#6b7280;border-bottom:1px solid #e5e7eb;">SLA Type</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#1f2937;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ $typeLabel }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 16px;background-color:#f9fafb;font-size:13px;color:#6b7280;border-bottom:1px solid #e5e7eb;">Urgency</td>
                                    <td style="padding:10px 16px;font-size:13px;color:{{ $urgency === 'breached' ? '#dc2626' : '#f59e0b' }};border-bottom:1px solid #e5e7eb;font-weight:700;">{{ ucfirst($urgency) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 16px;background-color:#f9fafb;font-size:13px;color:#6b7280;border-bottom:1px solid #e5e7eb;">Deadline</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#1f2937;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ $deadline->format('M d, Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 16px;background-color:#f9fafb;font-size:13px;color:#6b7280;border-bottom:1px solid #e5e7eb;">Return Scope</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#1f2937;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ $scope }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 16px;background-color:#f9fafb;font-size:13px;color:#6b7280;border-bottom:1px solid #e5e7eb;">Reason</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#1f2937;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ $rr->reason_label }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 16px;background-color:#f9fafb;font-size:13px;color:#6b7280;">Customer</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#1f2937;font-weight:600;">{{ $order->customer?->name ?? 'N/A' }}</td>
                                </tr>
                            </table>

                            {{-- CTA --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ route('admin.refunds.show', $rr) }}"
                                           style="display:inline-block;padding:12px 24px;background-color:{{ $headerColor }};color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">
                                            View Return #{{ $rr->id }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:16px 30px;background-color:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;">
                            <p style="margin:0;font-size:12px;color:#9ca3af;">This is an automated SLA escalation alert from {{ config('app.name') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
