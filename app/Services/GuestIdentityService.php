<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class GuestIdentityService
{
    public const COOKIE_NAME = 'grade_guest_id';
    private const COOKIE_TTL_MINUTES = 60 * 24 * 365; // 1 ano

    private function normalizeUuid(?string $raw): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        if (!Str::isUuid($value) && str_starts_with($value, 'guest_')) {
            $candidate = (string) substr($value, 6);
            if (Str::isUuid($candidate)) {
                $value = $candidate;
            }
        }

        return Str::isUuid($value) ? $value : null;
    }

    public function ensureGuestUuid(Request $request): string
    {
        $sessionUuid = $this->normalizeUuid((string) $request->session()->get('guest_tenant_uuid', ''));
        $cookieUuid = $this->normalizeUuid((string) $request->cookie(self::COOKIE_NAME, ''));

        $guestUuid = $sessionUuid ?: $cookieUuid ?: (string) Str::uuid();

        $request->session()->put('guest_tenant_uuid', $guestUuid);
        $request->session()->put('tenant_uuid', $guestUuid);

        if ($cookieUuid !== $guestUuid) {
            Cookie::queue(cookie(
                self::COOKIE_NAME,
                $guestUuid,
                self::COOKIE_TTL_MINUTES,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'Lax'
            ));
        }

        return $guestUuid;
    }
}

