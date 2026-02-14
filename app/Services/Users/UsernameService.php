<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Str;

class UsernameService
{
    public const MAX_LEN = 64;

    /**
     * Normalize to a safe, stable, ASCII username.
     * Allowed chars: [a-z0-9._-]
     */
    public function normalize(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }

        $s = Str::transliterate($s);
        $s = Str::lower($s);
        $s = preg_replace('/\\s+/', '.', $s) ?? $s;
        $s = preg_replace('/[^a-z0-9._-]+/', '', $s) ?? $s;
        $s = preg_replace('/\\.+/', '.', $s) ?? $s;
        $s = preg_replace('/_+/', '_', $s) ?? $s;
        $s = preg_replace('/-+/', '-', $s) ?? $s;
        $s = trim($s, "._-");

        if ($s === '') {
            return '';
        }

        return substr($s, 0, self::MAX_LEN);
    }

    public function baseFromEmail(string $email): string
    {
        $email = trim(mb_strtolower($email));
        $prefix = $email;
        if (str_contains($email, '@')) {
            $prefix = explode('@', $email, 2)[0] ?? $email;
        }
        if (str_contains($prefix, '+')) {
            $prefix = explode('+', $prefix, 2)[0] ?? $prefix;
        }

        $base = $this->normalize($prefix);
        return $base !== '' ? $base : 'user';
    }

    public function generateUniqueFromEmail(string $email, ?int $ignoreUserId = null): string
    {
        return $this->ensureUnique($this->baseFromEmail($email), $ignoreUserId);
    }

    public function ensureUnique(string $base, ?int $ignoreUserId = null): string
    {
        $base = $this->normalize($base);
        if ($base === '') {
            $base = 'user';
        }

        $candidate = $base;
        if (!$this->exists($candidate, $ignoreUserId)) {
            return $candidate;
        }

        // If base is already taken, append an incremental number: base1, base2, base3...
        for ($n = 1; $n < 10_000; $n++) {
            $suffix = (string) $n;
            $maxBase = self::MAX_LEN - strlen($suffix);
            $candidate = substr($base, 0, max(1, $maxBase)) . $suffix;
            if (!$this->exists($candidate, $ignoreUserId)) {
                return $candidate;
            }
        }

        // Extreme fallback.
        return substr($base, 0, max(1, self::MAX_LEN - 5)) . Str::lower(Str::random(5));
    }

    private function exists(string $username, ?int $ignoreUserId = null): bool
    {
        $q = User::query()->where('username', $username);
        if ($ignoreUserId) {
            $q->where('id', '!=', $ignoreUserId);
        }
        return $q->exists();
    }
}
