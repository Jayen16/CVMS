<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $mode === 'link' ? "Activate your {$shortName} account" : ($mode === 'code' ? "Your {$shortName} account activation code" : "Your {$shortName} password reset code") }}</title>
</head>
<body style="margin:0; padding:24px 0; background-color:#f3f7f6; color:#0f172a; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
        <tr>
            <td align="center" style="padding:0 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; border-collapse:collapse;">
                    <tr>
                        <td style="padding-bottom:20px;">
                            <div style="display:inline-block; padding:8px 14px; border-radius:999px; background-color:#d1fae5; color:#065f46; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase;">
                                {{ $shortName }}
                            </div>
                            <h1 style="margin:16px 0 6px; font-size:28px; line-height:1.2; color:#0f172a;">
                                {{ $mode === 'link' ? "Activate your {$shortName} account" : ($mode === 'code' ? 'Verify your account' : 'Reset your password') }}
                            </h1>
                            <p style="margin:0; font-size:15px; line-height:1.6; color:#475569;">
                                {{ $systemName }} for {{ $rhuName }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff; border:1px solid #dbe7e3; border-radius:24px; padding:32px;">
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.7; color:#0f172a;">
                                Hello{{ filled($recipientName) ? ' '.$recipientName : '' }},
                            </p>

                            @if ($mode === 'link')
                                <p style="margin:0 0 24px; font-size:16px; line-height:1.7; color:#334155;">
                                    You can now activate your account using your registered email address. Use the button below to receive your activation code and create your password.
                                </p>
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px; border-collapse:collapse;">
                                    <tr>
                                        <td align="center" style="border-radius:14px; background-color:#0f766e;">
                                            <a href="{{ $actionUrl }}" style="display:inline-block; padding:14px 24px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none;">Activate my account</a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0 0 10px; font-size:14px; line-height:1.7; color:#475569;">If the button does not open, copy and paste this link into your browser:</p>
                                <p style="margin:0; word-break:break-all; font-size:14px; line-height:1.7;"><a href="{{ $actionUrl }}" style="color:#0f766e; text-decoration:underline;">{{ $actionUrl }}</a></p>
                            @else
                                <p style="margin:0 0 16px; font-size:16px; line-height:1.7; color:#334155;">
                                    {{ $mode === 'code' ? 'Use this code to activate your CVMS account:' : 'Use this code to reset your CVMS password:' }}
                                </p>
                                <div style="margin:0 0 24px; padding:18px; border-radius:16px; background-color:#f0fdfa; border:1px solid #99f6e4; text-align:center;">
                                    <span style="font-size:28px; line-height:1.2; font-weight:700; letter-spacing:0.18em; color:#0f766e;">{{ $code }}</span>
                                </div>
                                <div style="margin:0; padding:16px 18px; border-radius:16px; background-color:#f8fafc; border:1px solid #e2e8f0;">
                                    <p style="margin:0 0 8px; font-size:14px; font-weight:700; color:#0f172a;">Important</p>
                                    <p style="margin:0; font-size:14px; line-height:1.7; color:#475569;">This code expires in 10 minutes. If you did not request this, you can ignore this email.</p>
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 4px 0; font-size:12px; line-height:1.7; color:#64748b;">
                            Sent by {{ $rhuName }} via {{ $appName }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
