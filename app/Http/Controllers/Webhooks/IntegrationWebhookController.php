<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\IntegrationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class IntegrationWebhookController extends Controller
{
    private const GLOBAL_TENANT_UUID = 'global';

    private function assertToken(Request $request, string $provider): void
    {
        $expected = (string) config("services.{$provider}.webhook_token", '');
        if ($expected === '') {
            // If not configured, accept (useful for local testing).
            return;
        }

        $token = (string) $request->header('X-Webhook-Token', $request->query('token', ''));
        if (!hash_equals($expected, (string) $token)) {
            abort(401, 'invalid_webhook_token');
        }
    }

    private function log(string $provider, Request $request, array $extra = []): void
    {
        $integration = Integration::query()
            ->where('tenant_uuid', self::GLOBAL_TENANT_UUID)
            ->where('provider', $provider)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first(['id']);

        IntegrationEvent::query()->create([
            'tenant_uuid' => self::GLOBAL_TENANT_UUID,
            'integration_id' => $integration ? (int) $integration->id : null,
            'event_type' => "webhook.{$provider}",
            'status' => 'info',
            'message' => Str::limit('Inbound webhook', 255, ''),
            'payload_json' => [
                'provider' => $provider,
                'ip' => $request->ip(),
                'headers' => Arr::only($request->headers->all(), ['user-agent', 'content-type', 'x-webhook-token']),
                'query' => $request->query(),
                'body' => $request->all(),
                'extra' => $extra,
            ],
            'occurred_at' => now(),
        ]);
    }

    public function smsGateway(Request $request): JsonResponse
    {
        $this->assertToken($request, 'sms_gateway');
        $this->log('sms_gateway', $request);
        return response()->json(['ok' => true]);
    }

    public function mailwizz(Request $request): JsonResponse
    {
        $this->assertToken($request, 'mailwizz');
        $this->log('mailwizz', $request);
        return response()->json(['ok' => true]);
    }
}

