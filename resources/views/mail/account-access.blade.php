<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isSetup ? 'Set up your account' : 'Reset your password' }}</title>
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
                                {{ $isSetup ? 'Set up your clinic account' : 'Reset your password' }}
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

                            @if ($isSetup)
                                <p style="margin:0 0 16px; font-size:16px; line-height:1.7; color:#334155;">
                                    A clinic staff account has been prepared for you in <strong>{{ $appName }}</strong>. Use the button below to create your password and finish account setup.
                                </p>
                                <p style="margin:0 0 24px; font-size:16px; line-height:1.7; color:#334155;">
                                    Once your password is set, you can sign in and start managing child vaccination records, clinic updates, and follow-up tasks.
                                </p>
                            @else
                                <p style="margin:0 0 16px; font-size:16px; line-height:1.7; color:#334155;">
                                    We received a request to reset the password for your <strong>{{ $appName }}</strong> account.
                                </p>
                                <p style="margin:0 0 24px; font-size:16px; line-height:1.7; color:#334155;">
                                    Use the button below to choose a new password and regain access securely.
                                </p>
                            @endif

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px; border-collapse:collapse;">
                                <tr>
                                    <td align="center" style="border-radius:14px; background:linear-gradient(135deg, #0f766e, #115e59);">
                                        <a href="{{ $actionUrl }}" style="display:inline-block; padding:14px 24px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none;">
                                            {{ $isSetup ? 'Create password' : 'Reset password' }}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <div style="margin:0 0 24px; padding:16px 18px; border-radius:16px; background-color:#f8fafc; border:1px solid #e2e8f0;">
                                <p style="margin:0 0 8px; font-size:14px; font-weight:700; color:#0f172a;">Important</p>
                                <p style="margin:0; font-size:14px; line-height:1.7; color:#475569;">
                                    This link will expire in {{ $expiresIn }} minutes.
                                    @if ($isSetup)
                                        If you were not expecting this invitation, you can ignore this email.
                                    @else
                                        If you did not request a password reset, you can ignore this email and your password will stay unchanged.
                                    @endif
                                </p>
                            </div>

                            <p style="margin:0 0 10px; font-size:14px; line-height:1.7; color:#475569;">
                                If the button does not open, copy and paste this link into your browser:
                            </p>
                            <p style="margin:0; word-break:break-all; font-size:14px; line-height:1.7;">
                                <a href="{{ $actionUrl }}" style="color:#0f766e; text-decoration:underline;">{{ $actionUrl }}</a>
                            </p>
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
