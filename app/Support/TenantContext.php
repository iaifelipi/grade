<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class TenantContext
{
    public static function tenantUuidOrNull(): ?string
    {
        $user = Auth::user();

        if (!$user) return null;

        return trim((string) $user->tenant_uuid) ?: null;
    }

    public static function requireTenantUuid(): string
    {
        $uuid = self::tenantUuidOrNull();

        if (!$uuid) {
            abort(403, 'Tenant não definido.');
        }

        return $uuid;
    }
}
