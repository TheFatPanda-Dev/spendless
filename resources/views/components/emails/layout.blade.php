@props([
    'title',
    'logoSrc' => null,
    'actionUrl' => null,
    'actionText' => null,
    'expiryText' => null,
])

@php
    $logoUrl = $logoSrc ?: asset('images/spendless_logo.png');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - SpendLess</title>
</head>
<body style="margin:0; padding:0; background-color:#f4fbf7; font-family:Arial, Helvetica, sans-serif; color:#111827;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:radial-gradient(circle at 18% 15%, rgba(16,185,129,0.16), transparent 45%), radial-gradient(circle at 85% 88%, rgba(16,185,129,0.1), transparent 45%), #f4fbf7; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px; background:#ffffff; border:1px solid #d1d5db; border-radius:16px; overflow:hidden;">
                <tr>
                    <td style="padding:24px 24px 18px 24px; border-bottom:1px solid #e5e7eb; background:#ffffff;">
                        <table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:0 auto;">
                            <tr>
                                <td style="vertical-align:middle; width:56px;">
                                    <img src="{{ $logoUrl }}" alt="SpendLess logo" width="48" height="48" style="display:block; border-radius:999px; border:1px solid #d1d5db; object-fit:contain;">
                                </td>
                                <td style="vertical-align:middle; padding-left:10px;">
                                    <span style="font-size:22px; font-weight:700; letter-spacing:-0.02em; color:#111827;">Spend</span><span style="font-size:22px; font-weight:700; letter-spacing:-0.02em; color:#10b981;">Less</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px 24px;">
                        <h1 style="margin:0 0 16px 0; font-size:26px; line-height:1.2; font-weight:700; color:#111827;">{{ $title }}</h1>

                        <div style="font-size:15px; line-height:1.7; color:#374151;">
                            {{ $slot }}
                        </div>

                        @if ($actionUrl && $actionText)
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                                <tr>
                                    <td align="center" style="border-radius:10px; background:#10b981;">
                                        <a href="{{ $actionUrl }}" style="display:inline-block; padding:12px 20px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">{{ $actionText }}</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:14px 0 0 0; font-size:12px; line-height:1.6; color:#6b7280; word-break:break-all;">
                                If the button doesn't work, copy and paste this link into your browser:<br>
                                <a href="{{ $actionUrl }}" style="color:#059669; text-decoration:underline;">{{ $actionUrl }}</a>
                            </p>
                        @endif

                        @if ($expiryText)
                            <p style="margin:16px 0 0 0; font-size:13px; line-height:1.6; color:#4b5563;">{{ $expiryText }}</p>
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px 24px 24px; border-top:1px solid #e5e7eb; background:#fafafa;">
                        <p style="margin:0; font-size:12px; line-height:1.6; color:#6b7280;">
                            You received this email from SpendLess. If this wasn’t expected, you can safely ignore it.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
