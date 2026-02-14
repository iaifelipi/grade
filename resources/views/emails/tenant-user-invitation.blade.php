<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tenant user invitation</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <h2 style="margin-bottom: 8px;">You were invited to access a tenant workspace</h2>

    <p style="margin: 0 0 10px 0;">
        Tenant: <strong>{{ $invitation->tenant_uuid }}</strong><br>
        Email: <strong>{{ $invitation->email }}</strong>
    </p>

    <p style="margin: 0 0 16px 0;">
        This invite expires on {{ optional($invitation->expires_at)->format('Y-m-d H:i') ?? 'soon' }}.
    </p>

    <p style="margin: 0 0 16px 0;">
        <a href="{{ $url }}" style="display:inline-block;padding:10px 14px;background:#8b5e34;color:#fff;text-decoration:none;border-radius:999px;">Accept invitation</a>
    </p>

    <p style="font-size: 12px; color: #6b7280;">
        If you were not expecting this invite, ignore this message.
    </p>
</body>
</html>
