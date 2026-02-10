<?php

namespace App\Services;

use App\Models\LeadRaw;
use App\Models\LeadSource;

class LeadSourceSemanticSuggestionService
{
    public function suggest(LeadSource $source): array
    {
        $leads = LeadRaw::where('tenant_uuid', $source->tenant_uuid)
            ->where('lead_source_id', $source->id)
            ->select('payload_json', 'name')
            ->limit(20000)
            ->get();

        return [
            'region'  => $this->detectRegion($leads),
            'segment' => $this->detectSegment($leads),
        ];
    }

    protected function detectRegion($leads): array
    {
        $cities = [];

        foreach ($leads as $l) {
            [$city, $uf] = $this->extractCityUf($l->payload_json);
            if (!$city) continue;

            $key = trim($city) . ($uf ? '-' . $uf : '');
            $cities[$key] = ($cities[$key] ?? 0) + 1;
        }

        if (!$cities) return [];

        arsort($cities);
        return [array_key_first($cities)];
    }

    protected function detectSegment($leads): array
    {
        $dictionary = [
            'constru' => 'Construção Civil',
            'obra'    => 'Construção Civil',
            'imobili' => 'Imobiliário',
            'engenh'  => 'Engenharia',
            'esquadr' => 'Esquadria',
            'vidra'   => 'Vidraçaria',
            'metal'   => 'Metalúrgica',
            'auto'    => 'Automotivo',
            'saude'   => 'Saúde',
            'educa'   => 'Educação',
        ];

        $hits = [];

        foreach ($leads as $l) {
            if (!$l->name) continue;

            $n = mb_strtolower($l->name);

            foreach ($dictionary as $needle => $segment) {
                if (str_contains($n, $needle)) {
                    $hits[$segment] = ($hits[$segment] ?? 0) + 1;
                }
            }
        }

        if (!$hits) return [];

        arsort($hits);
        return [array_key_first($hits)];
    }

    private function extractCityUf($payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $city = $this->firstPayloadValue($payload, [
            'CIDADE', 'Cidade', 'city', 'MUNICIPIO', '"ï»¿CIDADE"'
        ]);

        $uf = $this->firstPayloadValue($payload, [
            'ESTADO', 'Estado', 'UF', 'uf', '"ï»¿ESTADO"', '"ï»¿UF"'
        ]);

        $city = is_string($city) ? trim($city) : '';
        $uf = is_string($uf) ? strtoupper(trim($uf)) : '';
        $uf = $uf !== '' ? substr($uf, 0, 2) : '';

        return [$city, $uf];
    }

    private function firstPayloadValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $value = $payload[$key];
                if (is_string($value)) {
                    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
                }
                return is_scalar($value) ? (string) $value : null;
            }
        }
        return null;
    }
}
