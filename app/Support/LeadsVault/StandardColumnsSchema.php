<?php

namespace App\Support\LeadsVault;

final class StandardColumnsSchema
{
    /**
     * @return array<string,list<string>>
     */
    public static function aliases(): array
    {
        return [
            'nome' => ['nome', 'name', 'lead'],
            'cpf' => ['cpf'],
            'email' => ['email', 'e-mail', 'mail'],
            'phone' => ['phone', 'telefone', 'tel', 'fone', 'celular', 'phone_e164'],
            'data_nascimento' => ['data_nascimento', 'data de nascimento', 'datanascimento', 'nascimento', 'birth_date', 'birthdate', 'date_of_birth', 'dob'],
            'sex' => ['sex', 'sexo', 'genero', 'gênero'],
            'score' => ['score', 'pontuacao', 'pontuação'],
        ];
    }

    public static function canonicalFromKey(string $key): ?string
    {
        $normalized = self::normalizeKey($key);
        if ($normalized === '') {
            return null;
        }

        foreach (self::aliases() as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (self::normalizeKey($alias) === $normalized) {
                    return $canonical;
                }
            }
        }

        return null;
    }

    public static function isStandard(string $key): bool
    {
        return self::canonicalFromKey($key) !== null;
    }

    public static function normalizeKey(string $key): string
    {
        $normalized = mb_strtolower(trim($key), 'UTF-8');
        $normalized = str_replace(['-', ' '], '_', $normalized);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($ascii) && $ascii !== '') {
            $normalized = strtolower($ascii);
        }
        $normalized = preg_replace('/[^a-z0-9_]+/', '', $normalized) ?? '';
        $normalized = preg_replace('/_+/', '_', $normalized) ?? '';

        return trim($normalized, '_');
    }
}

