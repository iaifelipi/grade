<?php

namespace App\Support\LeadsVault;

class LeadColumnDetector
{
    /**
     * Retorna:
     * [
     *   'cpf'        => 'Header',
     *   'email'      => 'Header',
     *   'phone'      => 'Header',
     *   'ddd'        => 'Header',
     *
     *   'name'       => 'Header',
     *   'full_name'  => 'Header',
     *   'first_name' => 'Header',
     *   'last_name'  => 'Header',
     *   'badge_name' => 'Header',
     *
     *   'birth_date' => 'Header',
     *
     *   // ✅ NOVO:
     *   'sex'        => 'Header',
     *   'city'       => 'Header',
     *   'uf'         => 'Header',
     * ]
     */
    public static function detect(array $headers): array
    {
        $headers = array_values(array_filter(array_map(fn ($h) => trim((string) $h), $headers)));

        if (empty($headers)) {
            return [
                'cpf' => null,
                'email' => null,
                'phone' => null,
                'ddd' => null,

                'name' => null,
                'full_name' => null,
                'first_name' => null,
                'last_name' => null,
                'badge_name' => null,

                'birth_date' => null,

                'sex' => null,
                'city' => null,
                'uf' => null,
            ];
        }

        // original => normalized
        $norm = [];
        foreach ($headers as $h) {
            $norm[$h] = self::normalizeHeaderKey($h);
        }

        /* =====================================================
         | ✅ PHONE: prioridade explícita
         ===================================================== */
        $phone = self::detectPhoneWithPriority($norm);

        /* =====================================================
         | ✅ DDD: detecta coluna separada de DDD
         ===================================================== */
        $ddd = self::detectDDDColumn($norm);

        /* =====================================================
         | ✅ Demais campos (score-based)
         ===================================================== */
        $syn = self::synonyms();

        $best = [
            'cpf' => ['header' => null, 'score' => -999],
            'email' => ['header' => null, 'score' => -999],
            'birth_date' => ['header' => null, 'score' => -999],

            // ✅ nomes
            'full_name' => ['header' => null, 'score' => -999],
            'first_name' => ['header' => null, 'score' => -999],
            'last_name' => ['header' => null, 'score' => -999],
            'badge_name' => ['header' => null, 'score' => -999],
            'name_fallback' => ['header' => null, 'score' => -999],

            // ✅ novos
            'sex' => ['header' => null, 'score' => -999],
            'city' => ['header' => null, 'score' => -999],
            'uf' => ['header' => null, 'score' => -999],
        ];

        foreach ($norm as $original => $key) {

            // CPF
            $sCpf = self::scoreField($key, $syn['cpf']);
            if ($sCpf > $best['cpf']['score']) {
                $best['cpf'] = ['header' => $original, 'score' => $sCpf];
            }

            // EMAIL
            $sEmail = self::scoreField($key, $syn['email']);
            if ($sEmail > $best['email']['score']) {
                $best['email'] = ['header' => $original, 'score' => $sEmail];
            }

            // BIRTH DATE
            $sBirth = self::scoreField($key, $syn['birth_date']);
            if ($sBirth > $best['birth_date']['score']) {
                $best['birth_date'] = ['header' => $original, 'score' => $sBirth];
            }

            // ✅ FULL NAME
            $sFull = self::scoreField($key, $syn['full_name']);
            if ($sFull > $best['full_name']['score']) {
                $best['full_name'] = ['header' => $original, 'score' => $sFull];
            }

            // ✅ FIRST NAME
            $sFirst = self::scoreField($key, $syn['first_name']);
            if ($sFirst > $best['first_name']['score']) {
                $best['first_name'] = ['header' => $original, 'score' => $sFirst];
            }

            // ✅ LAST NAME
            $sLast = self::scoreField($key, $syn['last_name']);
            if ($sLast > $best['last_name']['score']) {
                $best['last_name'] = ['header' => $original, 'score' => $sLast];
            }

            // ✅ BADGE NAME
            $sBadge = self::scoreField($key, $syn['badge_name']);
            if ($sBadge > $best['badge_name']['score']) {
                $best['badge_name'] = ['header' => $original, 'score' => $sBadge];
            }

            // ✅ fallback old name logic (quando coluna é "nome")
            $sNameFallback = self::scoreField($key, $syn['name_fallback']);
            if ($sNameFallback > $best['name_fallback']['score']) {
                $best['name_fallback'] = ['header' => $original, 'score' => $sNameFallback];
            }

            // ✅ SEX
            $sSex = self::scoreField($key, $syn['sex']);
            if ($sSex > $best['sex']['score']) {
                $best['sex'] = ['header' => $original, 'score' => $sSex];
            }

            // ✅ CITY
            $sCity = self::scoreField($key, $syn['city']);
            if ($sCity > $best['city']['score']) {
                $best['city'] = ['header' => $original, 'score' => $sCity];
            }

            // ✅ UF
            $sUf = self::scoreField($key, $syn['uf']);
            if ($sUf > $best['uf']['score']) {
                $best['uf'] = ['header' => $original, 'score' => $sUf];
            }
        }

        // ✅ thresholds
        $cpfHeader = $best['cpf']['score'] >= 30 ? $best['cpf']['header'] : null;
        $emailHeader = $best['email']['score'] >= 30 ? $best['email']['header'] : null;
        $birthHeader = $best['birth_date']['score'] >= 15 ? $best['birth_date']['header'] : null;

        $fullName = $best['full_name']['score'] >= 40 ? $best['full_name']['header'] : null;
        $firstName = $best['first_name']['score'] >= 35 ? $best['first_name']['header'] : null;
        $lastName = $best['last_name']['score'] >= 35 ? $best['last_name']['header'] : null;
        $badgeName = $best['badge_name']['score'] >= 35 ? $best['badge_name']['header'] : null;

        $fallbackName = $best['name_fallback']['score'] >= 30 ? $best['name_fallback']['header'] : null;

        // ✅ novos thresholds
        $sexHeader  = $best['sex']['score'] >= 25 ? $best['sex']['header'] : null;
        $cityHeader = $best['city']['score'] >= 25 ? $best['city']['header'] : null;
        $ufHeader   = $best['uf']['score'] >= 25 ? $best['uf']['header'] : null;

        /**
         * ✅ name FINAL (melhor escolha)
         */
        $finalNameHeader = $fullName ?: ($firstName && $lastName ? $firstName : null) ?: $badgeName ?: $fallbackName;

        return [
            'cpf' => $cpfHeader,
            'email' => $emailHeader,
            'phone' => $phone,
            'ddd' => $ddd,

            'name' => $finalNameHeader,
            'full_name' => $fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'badge_name' => $badgeName,

            'birth_date' => $birthHeader,

            'sex' => $sexHeader,
            'city' => $cityHeader,
            'uf' => $ufHeader,
        ];
    }

    /* =====================================================
     | ✅ PHONE PRIORITY LOGIC (CANON)
     ===================================================== */
    private static function detectPhoneWithPriority(array $norm): ?string
    {
        $priority = [
            ['tel_celular', 'telefone_celular', 'celular', 'mobile', 'whatsapp', 'fone_celular'],
            ['tel_comercial', 'telefone_comercial', 'comercial', 'fone_comercial'],
            ['tel_residencial', 'telefone_residencial', 'residencial', 'fone_residencial'],
            ['telefone', 'fone', 'tel', 'phone'],
        ];

        foreach ($priority as $group) {
            foreach ($norm as $original => $key) {
                foreach ($group as $needle) {
                    if ($needle !== '' && str_contains($key, $needle)) {
                        return $original;
                    }
                }
            }
        }

        return null;
    }

    /* =====================================================
     | ✅ DDD DETECTOR
     ===================================================== */
    private static function detectDDDColumn(array $norm): ?string
    {
        $dddKeys = [
            'ddd',
            'ddd_tel',
            'ddd_telefone',
            'ddd_celular',
            'ddd_phone',
            'ddd_fone',
            'ddd_residencial',
            'ddd_comercial',
        ];

        foreach ($norm as $original => $key) {
            if (in_array($key, $dddKeys, true)) {
                return $original;
            }

            if (
                str_contains($key, 'ddd') &&
                (
                    str_contains($key, 'tel') ||
                    str_contains($key, 'fone') ||
                    str_contains($key, 'telefone') ||
                    str_contains($key, 'celular') ||
                    $key === 'ddd'
                )
            ) {
                return $original;
            }
        }

        return null;
    }

    /* =====================================================
     | Utils
     ===================================================== */
    private static function scoreField(string $normalizedKey, array $rules): int
    {
        $score = 0;

        foreach ($rules['exact'] as $n) {
            if ($normalizedKey === $n) $score += 100;
        }
        foreach ($rules['contains'] as $n) {
            if ($n !== '' && str_contains($normalizedKey, $n)) $score += 40;
        }
        foreach ($rules['prefix'] as $n) {
            if ($n !== '' && str_starts_with($normalizedKey, $n)) $score += 35;
        }
        foreach ($rules['negative'] as $n) {
            if ($n !== '' && str_contains($normalizedKey, $n)) $score -= 30;
        }

        return $score;
    }

    private static function normalizeHeaderKey(string $h): string
    {
        $h = mb_strtolower(trim($h));
        $h = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $h);
        $h = preg_replace('/[^a-z0-9]+/', '_', $h);
        return trim((string) $h, '_');
    }

    private static function synonyms(): array
    {
        return [
            'cpf' => [
                'exact' => ['cpf', 'cpf_cliente', 'cpfcnpj', 'documento', 'doc'],
                'contains' => ['cpf', 'doc'],
                'prefix' => ['cpf', 'doc'],
                'negative' => ['cnpj'],
            ],
            'email' => [
                'exact' => ['email', 'e_mail', 'mail'],
                'contains' => ['email', 'mail'],
                'prefix' => ['email'],
                'negative' => [],
            ],

            // ✅ Names
            'full_name' => [
                'exact' => ['nome_completo', 'nomecompleto', 'name', 'full_name', 'fullname', 'nm_cliente', 'nome_cliente'],
                'contains' => ['nome_completo', 'nomecompleto', 'full'],
                'prefix' => ['nome_completo', 'nomecompleto'],
                'negative' => ['mae','pai','razao','empresa','fantasia','responsavel','nome_cracha','nomecracha'],
            ],
            'first_name' => [
                'exact' => ['primeiro_nome','primeironome','first_name','firstname','nome'],
                'contains' => ['primeiro','first','nome'],
                'prefix' => ['primeiro_nome','first_name','nome'],
                'negative' => ['sobrenome','segundo_nome','ultimo_nome','last','mae','pai','razao','empresa','nome_cracha','nomecracha'],
            ],
            'last_name' => [
                'exact' => ['sobrenome','segundo_nome','ultimonome','ultimo_nome','last_name','lastname'],
                'contains' => ['sobrenome','segundo','ultimo','last'],
                'prefix' => ['sobrenome','ultimo_nome','last_name'],
                'negative' => ['mae','pai','razao','empresa'],
            ],
            'badge_name' => [
                'exact' => ['nomecracha','nome_cracha','name_badge','cracha'],
                'contains' => ['cracha','badge'],
                'prefix' => ['nome_cracha','nomecracha'],
                'negative' => ['mae','pai','razao','empresa'],
            ],
            'name_fallback' => [
                'exact' => ['nome', 'name', 'firstname', 'primeironome'],
                'contains' => ['nome', 'name'],
                'prefix' => ['nome', 'name'],
                'negative' => ['mae', 'pai', 'razao', 'empresa'],
            ],
            'birth_date' => [
                'exact' => ['data_nascimento','nascimento','birth_date','dob','datanasc','data_nasc','dt_nasc','dt_nascimento','data_de_nascimento'],
                'contains' => ['nascimento','birth','datanasc','dt_nasc','dt_nascimento'],
                'prefix' => ['data_nasc','datanasc','dt_nasc'],
                'negative' => [],
            ],

            // ✅ NOVO: SEX / CITY / UF
            'sex' => [
                'exact' => ['sexo', 'genero', 'gênero', 'gender', 'sex'],
                'contains' => ['sexo', 'gener', 'gender', 'sex'],
                'prefix' => ['sexo', 'genero', 'gender'],
                'negative' => [],
            ],
            'city' => [
                'exact' => ['cidade', 'municipio', 'município', 'city', 'mun'],
                'contains' => ['cidade', 'municip', 'city'],
                'prefix' => ['cidade', 'municipio', 'municipio'],
                'negative' => [],
            ],
            'uf' => [
                'exact' => ['uf', 'estado', 'state'],
                'contains' => ['uf', 'estado'],
                'prefix' => ['uf'],
                'negative' => [],
            ],
        ];
    }
}
