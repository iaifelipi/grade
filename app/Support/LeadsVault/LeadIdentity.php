<?php



namespace App\Support\LeadsVault;

use App\Support\Brazil\Cpf;


class LeadIdentity

{

    public static function normalizeCPF(?string $v): ?string

    {
        return Cpf::normalize($v);

    }



    public static function normalizeEmail(?string $v): ?string

    {

        $v = trim((string) $v);

        if ($v === '') return null;



        $v = mb_strtolower($v);

        $v = str_replace(' ', '', $v);



        if (!str_contains($v, '@')) return null;

        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) return null;



        return $v;

    }



    /**

     * ✅ CANON Grade: normaliza para E.164 BR

     *

     * REGRA:

     * 1) limpar tudo e ficar só dígitos

     * 2) remover prefixos lixo: 00, 055

     * 3) se começar com 55 → já é BR

     * 4) se tiver 12/13 dígitos e começa com 55 → OK (55 + DDD + 8/9)

     * 5) se tiver 10/11 dígitos e NÃO começa com 55 → é DDD + 8/9

     * 6) se tiver 8/9 dígitos → usar DDD padrão do tenant (se vier)

     *

     * @param string|null $v Número bruto

     * @param string|null $dddDefault Ex: "11", "21"

     * @param string|null $dddFromRow Ex: "11" (coluna DDD do arquivo)

     */

    public static function normalizePhoneE164BR(?string $v, ?string $dddDefault = null, ?string $dddFromRow = null): ?string

    {

        $v = trim((string) $v);

        if ($v === '') return null;



        // 1) só dígitos

        $digits = preg_replace('/\D+/', '', $v);

        if (!$digits) return null;



        // 2) remover prefixos lixo

        // "00" discagem internacional (muito comum)

        while (str_starts_with($digits, '00')) {

            $digits = substr($digits, 2);

        }



        // "055" lixo comum em base ruim

        while (str_starts_with($digits, '055')) {

            $digits = substr($digits, 3);

        }



        // sanitiza DDDs

        $dddFromRow = $dddFromRow ? preg_replace('/\D+/', '', $dddFromRow) : null;

        $dddDefault = $dddDefault ? preg_replace('/\D+/', '', $dddDefault) : null;



        if ($dddFromRow !== null && strlen($dddFromRow) !== 2) $dddFromRow = null;

        if ($dddDefault !== null && strlen($dddDefault) !== 2) $dddDefault = null;



        // helper: DDD a usar (prioridade: linha > config)

        $ddd = $dddFromRow ?: $dddDefault;



        /**

         * 3) se começa com 55 → já é BR

         * pode vir como:

         * 55 + DDD + 9 (13)

         * 55 + DDD + 8 (12)

         */

        if (str_starts_with($digits, '55')) {

            $rest = substr($digits, 2);



            // 4/5) se rest tiver 10/11: OK

            if (strlen($rest) === 10 || strlen($rest) === 11) {

                return '+55' . $rest;

            }



            // se veio "55" mas tamanho lixo: inválido

            return null;

        }



        /**

         * 6/7) sem 55:

         * - 11 dígitos => DDD + celular(9)

         * - 10 dígitos => DDD + fixo(8)

         */

        if (strlen($digits) === 10 || strlen($digits) === 11) {

            return '+55' . $digits;

        }



        /**

         * 8) 8 ou 9 dígitos (sem DDD) => usa DDD padrão

         */

        if (strlen($digits) === 8 || strlen($digits) === 9) {

            if (!$ddd) return null;

            return '+55' . $ddd . $digits;

        }



        return null;

    }



    /**

     * ✅ identity_key CANON (SEM CNPJ):

     * - prioriza CPF

     * - senão EMAIL

     * - senão PHONE

     */

    public static function buildIdentityKey(?string $cpf, ?string $email, ?string $phoneE164): ?string

    {

        if ($cpf) return 'cpf:' . $cpf;

        if ($email) return 'email:' . $email;

        if ($phoneE164) return 'phone:' . $phoneE164;

        return null;

    }

}
