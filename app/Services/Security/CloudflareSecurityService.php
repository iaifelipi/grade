<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CloudflareSecurityService
{
    private function token(): string
    {
        return (string) config('security_monitoring.cloudflare.api_token', '');
    }

    private function zoneId(): string
    {
        return (string) config('security_monitoring.cloudflare.zone_id', '');
    }

    public function isEnabled(): bool
    {
        return trim($this->token()) !== '' && trim($this->zoneId()) !== '';
    }

    /**
     * @return array{ok:bool, message:string, created:int}
     */
    public function ingestFirewallEvents(SecurityAccessEventWriter $writer, int $minutes = 15, int $limit = 250): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'message' => 'Cloudflare não configurado.', 'created' => 0];
        }

        // Cloudflare GraphQL API. Availability depends on plan and token scopes.
        $from = now()->subMinutes(max(1, $minutes))->toIso8601String();
        $to = now()->toIso8601String();
        $limit = max(1, min(1000, $limit));

        $query = <<<'GQL'
query($zoneTag: String!, $from: DateTime!, $to: DateTime!, $limit: Int!) {
  viewer {
    zones(filter: { zoneTag: $zoneTag }) {
      firewallEventsAdaptive(
        filter: { datetime_geq: $from, datetime_leq: $to }
        limit: $limit
        orderBy: [ datetime_DESC ]
      ) {
        action
        clientAsn
        clientCountryName
        clientIP
        datetime
        userAgent
        httpMethod
        uri
        source
        edgeResponseStatus
        rayName
        ruleId
        ruleMessage
      }
    }
  }
}
GQL;

        $executionId = (string) Str::uuid();

        try {
            $res = Http::timeout(20)
                ->withToken($this->token())
                ->acceptJson()
                ->post('https://api.cloudflare.com/client/v4/graphql', [
                    'query' => $query,
                    'variables' => [
                        'zoneTag' => $this->zoneId(),
                        'from' => $from,
                        'to' => $to,
                        'limit' => $limit,
                    ],
                ]);

            if (!$res->ok()) {
                return [
                    'ok' => false,
                    'message' => 'Cloudflare GraphQL retornou HTTP ' . $res->status(),
                    'created' => 0,
                ];
            }

            $data = $res->json();
            $events = $data['data']['viewer']['zones'][0]['firewallEventsAdaptive'] ?? [];
            if (!is_array($events)) {
                $events = [];
            }

            $created = 0;
            foreach ($events as $ev) {
                if (!is_array($ev)) {
                    continue;
                }
                $ip = (string) ($ev['clientIP'] ?? '');
                if ($ip === '') {
                    continue;
                }

                $writer->write([
                    'source' => 'cloudflare',
                    'event_type' => 'firewall_event',
                    'ip_address' => $ip,
                    'request_path' => isset($ev['uri']) ? (string) $ev['uri'] : null,
                    'request_method' => isset($ev['httpMethod']) ? (string) $ev['httpMethod'] : null,
                    'http_status' => isset($ev['edgeResponseStatus']) ? (int) $ev['edgeResponseStatus'] : null,
                    'country' => isset($ev['clientCountryName']) ? (string) $ev['clientCountryName'] : null,
                    'asn' => isset($ev['clientAsn']) ? (string) $ev['clientAsn'] : null,
                    'user_agent' => isset($ev['userAgent']) ? (string) $ev['userAgent'] : null,
                    'occurred_at' => isset($ev['datetime']) ? (string) $ev['datetime'] : now(),
                    'payload_json' => [
                        'execution_id' => $executionId,
                        'action' => $ev['action'] ?? null,
                        'source' => $ev['source'] ?? null,
                        'ray' => $ev['rayName'] ?? null,
                        'rule_id' => $ev['ruleId'] ?? null,
                        'rule_message' => $ev['ruleMessage'] ?? null,
                    ],
                ]);
                $created++;
            }

            return [
                'ok' => true,
                'message' => "Eventos Cloudflare ingeridos: {$created}",
                'created' => $created,
            ];
        } catch (\Throwable $e) {
            Log::warning('cloudflare ingest failed', [
                'execution_id' => $executionId,
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'message' => 'Falha ao ingerir Cloudflare: ' . $e->getMessage(), 'created' => 0];
        }
    }

    /**
     * @return array{ok:bool, message:string, rule_id:?string}
     */
    public function ensureIpRule(string $mode, string $ip, string $notes = 'Grade security panel'): array
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['block', 'challenge'], true)) {
            return ['ok' => false, 'message' => 'Modo inválido.', 'rule_id' => null];
        }
        $ip = trim($ip);
        if ($ip === '') {
            return ['ok' => false, 'message' => 'IP inválido.', 'rule_id' => null];
        }
        if (!$this->isEnabled()) {
            return ['ok' => false, 'message' => 'Cloudflare não configurado.', 'rule_id' => null];
        }

        try {
            $zone = $this->zoneId();
            $base = "https://api.cloudflare.com/client/v4/zones/{$zone}/firewall/access_rules/rules";

            // Check existing rule.
            $existing = Http::timeout(20)
                ->withToken($this->token())
                ->acceptJson()
                ->get($base, [
                    'configuration.target' => 'ip',
                    'configuration.value' => $ip,
                    'per_page' => 1,
                ]);

            if ($existing->ok()) {
                $first = $existing->json('result.0');
                if (is_array($first) && isset($first['id'])) {
                    $id = (string) $first['id'];
                    $currentMode = (string) ($first['mode'] ?? '');
                    if ($currentMode === $mode) {
                        return ['ok' => true, 'message' => 'Regra já existe.', 'rule_id' => $id];
                    }

                    // Update mode.
                    $put = Http::timeout(20)
                        ->withToken($this->token())
                        ->acceptJson()
                        ->put($base . '/' . $id, [
                            'mode' => $mode,
                            'configuration' => ['target' => 'ip', 'value' => $ip],
                            'notes' => $notes,
                        ]);
                    if ($put->ok()) {
                        return ['ok' => true, 'message' => 'Regra atualizada.', 'rule_id' => $id];
                    }
                }
            }

            // Create rule.
            $create = Http::timeout(20)
                ->withToken($this->token())
                ->acceptJson()
                ->post($base, [
                    'mode' => $mode,
                    'configuration' => ['target' => 'ip', 'value' => $ip],
                    'notes' => $notes,
                ]);

            if (!$create->ok()) {
                return ['ok' => false, 'message' => 'Cloudflare retornou HTTP ' . $create->status(), 'rule_id' => null];
            }

            $id = (string) ($create->json('result.id') ?? '');
            return ['ok' => true, 'message' => 'Regra criada.', 'rule_id' => $id !== '' ? $id : null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Falha Cloudflare: ' . $e->getMessage(), 'rule_id' => null];
        }
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public function removeIpRule(string $ip): array
    {
        $ip = trim($ip);
        if ($ip === '') {
            return ['ok' => false, 'message' => 'IP inválido.'];
        }
        if (!$this->isEnabled()) {
            return ['ok' => false, 'message' => 'Cloudflare não configurado.'];
        }

        try {
            $zone = $this->zoneId();
            $base = "https://api.cloudflare.com/client/v4/zones/{$zone}/firewall/access_rules/rules";

            $existing = Http::timeout(20)
                ->withToken($this->token())
                ->acceptJson()
                ->get($base, [
                    'configuration.target' => 'ip',
                    'configuration.value' => $ip,
                    'per_page' => 1,
                ]);

            $first = $existing->ok() ? $existing->json('result.0') : null;
            if (!is_array($first) || !isset($first['id'])) {
                return ['ok' => true, 'message' => 'Nenhuma regra encontrada (ok).'];
            }

            $id = (string) $first['id'];
            $del = Http::timeout(20)
                ->withToken($this->token())
                ->acceptJson()
                ->delete($base . '/' . $id);

            if (!$del->ok()) {
                return ['ok' => false, 'message' => 'Falha ao remover regra: HTTP ' . $del->status()];
            }

            return ['ok' => true, 'message' => 'Regra removida.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Falha Cloudflare: ' . $e->getMessage()];
        }
    }
}

