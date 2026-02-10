<?php

namespace App\Support\Brazil;

final class Cpf
{
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) !== 11) {
            return null;
        }

        return self::isValid($digits) ? $digits : null;
    }

    public static function isValid(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf)) {
            return false;
        }

        // Reject repeated sequences: 000..., 111..., etc.
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        $digits = array_map('intval', str_split($cpf));

        $sum = 0;
        for ($i = 0, $weight = 10; $i < 9; $i++, $weight--) {
            $sum += $digits[$i] * $weight;
        }
        $mod = $sum % 11;
        $check1 = $mod < 2 ? 0 : 11 - $mod;
        if ($digits[9] !== $check1) {
            return false;
        }

        $sum = 0;
        for ($i = 0, $weight = 11; $i < 10; $i++, $weight--) {
            $sum += $digits[$i] * $weight;
        }
        $mod = $sum % 11;
        $check2 = $mod < 2 ? 0 : 11 - $mod;

        return $digits[10] === $check2;
    }
}
