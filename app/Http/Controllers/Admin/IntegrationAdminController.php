<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\IntegrationEvent;
use App\Services\Admin\AdminAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class IntegrationAdminController extends Controller
{
    private const GLOBAL_TENANT_UUID = 'global';

    private function ensureSuperAdmin(): void
    {
        if (!auth()->check() || !auth()->user()?->isSuperAdmin() || session()->has('impersonate_user_id')) {
            abort(403, 'Acesso negado.');
        }
    }

    private function normalizeKey(string $raw): string
    {
        $s = trim($raw);
        $s = \Illuminate\Support\Str::transliterate($s);
        $s = \Illuminate\Support\Str::lower($s);
        $s = preg_replace('/\\s+/', '-', $s) ?? $s;
        $s = preg_replace('/[^a-z0-9._-]+/', '', $s) ?? $s;
        $s = preg_replace('/-+/', '-', $s) ?? $s;
        $s = trim($s, "._-");
        return substr($s, 0, 64);
    }

    private function providers(): array
    {
        return [
            'mailwizz' => [
                'label' => 'Mailwizz (Email marketing)',
                // MailWizz 2.x: X-Api-Key + API URL (optionally include /api/index.php if no clean URLs).
                'required' => ['base_url', 'api_key'],
            ],
            'sms_gateway' => [
                'label' => 'SMS Gateway',
                'required' => ['base_url', 'api_key'],
            ],
            'wasender' => [
                'label' => 'Wasender (WhatsApp)',
                'required' => ['base_url', 'token'],
            ],
            'custom' => [
                'label' => 'Custom (manual)',
                'required' => [],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,string>
     */
    private function normalizeSecrets(string $provider, array $raw): array
    {
        $secrets = collect($raw)
            ->mapWithKeys(fn ($v, $k) => [trim((string) $k) => trim((string) $v)])
            ->filter(fn ($v, $k) => $k !== '' && $v !== '')
            ->all();

        // Back-compat (older UI stored MailWizz api key in public_key).
        if ($provider === 'mailwizz' && empty($secrets['api_key']) && !empty($secrets['public_key'])) {
            $secrets['api_key'] = (string) $secrets['public_key'];
        }

        return $secrets;
    }

    public function index(Request $request)
    {
        $this->ensureSuperAdmin();

        $integrations = Integration::query()
            ->where('tenant_uuid', self::GLOBAL_TENANT_UUID)
            ->orderBy('provider')
            ->orderBy('name')
            ->get();

        $providers = $this->providers();

        $recentEvents = IntegrationEvent::query()
            ->where('tenant_uuid', self::GLOBAL_TENANT_UUID)
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return view('admin.integrations.index', compact('integrations', 'providers', 'recentEvents'));
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin();

        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_keys($this->providers()))],
            'key' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(['active', 'disabled'])],
            'secrets' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        $provider = (string) $data['provider'];
        $defs = $this->providers()[$provider] ?? null;
        if (!$defs) {
            abort(422, 'Provider inválido.');
        }

        $key = $this->normalizeKey((string) $data['key']);
        if ($key === '') {
            abort(422, 'Key inválida.');
        }
        $exists = Integration::query()
            ->where('tenant_uuid', self::GLOBAL_TENANT_UUID)
            ->where('key', $key)
            ->exists();
        if ($exists) {
            abort(422, 'Já existe uma integração com esta key.');
        }

        $secrets = $this->normalizeSecrets($provider, (array) ($data['secrets'] ?? []));

        foreach (($defs['required'] ?? []) as $requiredKey) {
            if (empty($secrets[$requiredKey])) {
                abort(422, "Campo obrigatório ausente em secrets: {$requiredKey}");
            }
        }

        $settings = collect($data['settings'] ?? [])
            ->mapWithKeys(fn ($v, $k) => [trim((string) $k) => is_string($v) ? trim($v) : $v])
            ->filter(fn ($v, $k) => $k !== '')
            ->all();

        $integration = new Integration();
        $integration->tenant_uuid = self::GLOBAL_TENANT_UUID;
        $integration->provider = $provider;
        $integration->key = $key;
        $integration->name = (string) $data['name'];
        $integration->status = (string) ($data['status'] ?? 'active');
        $integration->secrets_enc = $secrets ?: null;
        $integration->settings_json = $settings ?: null;
        $integration->last_tested_at = null;
        $integration->last_test_status = null;
        $integration->save();

        app(AdminAuditService::class)->log('admin.integration.created', null, [
            'tenant_uuid' => $integration->tenant_uuid,
            'integration_id' => $integration->id,
            'provider' => $integration->provider,
            'key' => $integration->key,
            'name' => $integration->name,
        ]);

        return back()->with('status', 'Integração criada.');
    }

    public function update(Request $request, int $id)
    {
        $this->ensureSuperAdmin();

        $integration = Integration::query()->findOrFail($id);
        if ((string) ($integration->tenant_uuid ?? '') !== self::GLOBAL_TENANT_UUID) {
            abort(403, 'Integração inválida (não-global).');
        }

        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_keys($this->providers()))],
            'key' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(['active', 'disabled'])],
            'secrets' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        $provider = (string) $data['provider'];
        $defs = $this->providers()[$provider] ?? null;
        if (!$defs) {
            abort(422, 'Provider inválido.');
        }

        $key = $this->normalizeKey((string) $data['key']);
        if ($key === '') {
            abort(422, 'Key inválida.');
        }
        $exists = Integration::query()
            ->where('tenant_uuid', self::GLOBAL_TENANT_UUID)
            ->where('key', $key)
            ->where('id', '!=', $integration->id)
            ->exists();
        if ($exists) {
            abort(422, 'Já existe uma integração com esta key.');
        }

        $existingSecrets = (array) ($integration->secrets_enc ?? []);
        $incomingSecrets = $this->normalizeSecrets($provider, (array) ($data['secrets'] ?? []));
        // Merge: blank inputs keep current values; filled inputs override.
        $secrets = array_merge($existingSecrets, $incomingSecrets);
        $secrets = $this->normalizeSecrets($provider, $secrets);

        foreach (($defs['required'] ?? []) as $requiredKey) {
            if (empty($secrets[$requiredKey])) {
                abort(422, "Campo obrigatório ausente em secrets: {$requiredKey}");
            }
        }

        $settings = collect($data['settings'] ?? [])
            ->mapWithKeys(fn ($v, $k) => [trim((string) $k) => is_string($v) ? trim($v) : $v])
            ->filter(fn ($v, $k) => $k !== '')
            ->all();

        $integration->provider = $provider;
        $integration->key = $key;
        $integration->name = (string) $data['name'];
        $integration->status = (string) ($data['status'] ?? 'active');
        $integration->secrets_enc = $secrets ?: null;
        $integration->settings_json = $settings ?: null;
        $integration->save();

        app(AdminAuditService::class)->log('admin.integration.updated', null, [
            'tenant_uuid' => $integration->tenant_uuid,
            'integration_id' => $integration->id,
            'provider' => $integration->provider,
            'key' => $integration->key,
            'name' => $integration->name,
            'status' => $integration->status,
        ]);

        return back()->with('status', 'Integração atualizada.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureSuperAdmin();

        $integration = Integration::query()->findOrFail($id);
        if ((string) ($integration->tenant_uuid ?? '') !== self::GLOBAL_TENANT_UUID) {
            abort(403, 'Integração inválida (não-global).');
        }

        $payload = [
            'tenant_uuid' => $integration->tenant_uuid,
            'integration_id' => $integration->id,
            'provider' => $integration->provider,
            'key' => $integration->key,
            'name' => $integration->name,
        ];

        $integration->delete();

        app(AdminAuditService::class)->log('admin.integration.deleted', null, $payload);

        return back()->with('status', 'Integração removida.');
    }

    public function test(Request $request, int $id)
    {
        $this->ensureSuperAdmin();

        $integration = Integration::query()->findOrFail($id);
        if ((string) ($integration->tenant_uuid ?? '') !== self::GLOBAL_TENANT_UUID) {
            abort(403, 'Integração inválida (não-global).');
        }

        $defs = $this->providers()[$integration->provider] ?? null;
        $secrets = $this->normalizeSecrets((string) $integration->provider, (array) ($integration->secrets_enc ?? []));

        $ok = true;
        $missing = [];
        foreach (($defs['required'] ?? []) as $requiredKey) {
            if (empty($secrets[$requiredKey])) {
                $ok = false;
                $missing[] = $requiredKey;
            }
        }

        $httpStatus = null;
        $message = null;
        $payload = null;
        if ($ok) {
            try {
                $timeout = (int) (($integration->settings_json['timeout'] ?? null) ?: 10);
                $timeout = max(2, min(60, $timeout));

                if ($integration->provider === 'mailwizz') {
                    $apiUrl = rtrim((string) ($secrets['base_url'] ?? ''), '/');
                    $apiKey = (string) ($secrets['api_key'] ?? '');
                    $url = $apiUrl . '/lists';

                    $res = Http::timeout($timeout)
                        ->acceptJson()
                        ->withHeaders([
                            'X-Api-Key' => $apiKey,
                        ])
                        ->get($url, ['page' => 1, 'per_page' => 1]);

                    $httpStatus = $res->status();
                    $json = $res->json();
                    $status = is_array($json) ? (string) ($json['status'] ?? '') : '';

                    $ok = $res->ok() && $status === 'success';
                    $message = $ok
                        ? 'Mailwizz: conexão OK (GET /lists).'
                        : ('Mailwizz: falhou (HTTP ' . $httpStatus . ').');

                    $payload = [
                        'provider' => 'mailwizz',
                        'http_status' => $httpStatus,
                        'endpoint' => '/lists',
                        'response_status' => $status !== '' ? $status : null,
                    ];
                } else {
                    $message = 'Configuração básica OK.';
                }
            } catch (\Throwable $e) {
                $ok = false;
                $message = 'Erro ao testar integração: ' . ($e->getMessage() ?: 'erro desconhecido');
                $payload = [
                    'provider' => (string) $integration->provider,
                    'exception' => get_class($e),
                ];
            }
        } else {
            $message = 'Campos ausentes: ' . implode(', ', $missing);
            $payload = ['missing' => $missing];
        }

        $integration->last_tested_at = now();
        $integration->last_test_status = $ok ? 'ok' : 'error';
        $integration->save();

        IntegrationEvent::create([
            'tenant_uuid' => self::GLOBAL_TENANT_UUID,
            'integration_id' => $integration->id,
            'event_type' => 'test',
            'status' => $ok ? 'ok' : 'error',
            'message' => $message,
            'payload_json' => $payload,
            'occurred_at' => now(),
        ]);

        app(AdminAuditService::class)->log('admin.integration.tested', null, [
            'tenant_uuid' => $integration->tenant_uuid,
            'integration_id' => $integration->id,
            'provider' => $integration->provider,
            'key' => $integration->key,
            'status' => $integration->last_test_status,
        ]);

        return back()->with('status', $ok ? 'Teste OK.' : 'Teste falhou.');
    }
}
