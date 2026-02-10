<?php

namespace App\Services\LeadsVault;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AutomationChannelDispatchService
{
    /**
     * @param array<string,mixed> $config
     * @param array<string,mixed> $context
     * @return array{status:string,error:?string,response:array<string,mixed>,external_ref:?string}
     */
    public function dispatch(string $channel, object $lead, array $config = [], array $context = []): array
    {
        $normalized = Str::lower(trim($channel));

        return match ($normalized) {
            'email' => $this->dispatchEmail($lead, $config),
            'sms' => $this->dispatchWebhookChannel('sms', $lead, $config, $context),
            'whatsapp' => $this->dispatchWebhookChannel('whatsapp', $lead, $config, $context),
            default => [
                'status' => 'failed',
                'error' => "Canal não suportado para dispatch: {$normalized}",
                'response' => ['mode' => 'invalid_channel'],
                'external_ref' => null,
            ],
        };
    }

    /**
     * @param array<string,mixed> $config
     * @return array{status:string,error:?string,response:array<string,mixed>,external_ref:?string}
     */
    private function dispatchEmail(object $lead, array $config): array
    {
        $to = trim((string) ($lead->email ?? ''));
        if ($to === '') {
            return [
                'status' => 'failed',
                'error' => 'Contato sem email.',
                'response' => ['mode' => 'validation'],
                'external_ref' => null,
            ];
        }

        $subjectTemplate = (string) ($config['subject'] ?? config('automation_dispatch.email.default_subject'));
        $bodyTemplate = (string) ($config['message'] ?? config('automation_dispatch.email.default_message'));
        $subject = $this->renderTemplate($subjectTemplate, $lead);
        $body = $this->renderTemplate($bodyTemplate, $lead);

        try {
            Mail::raw($body, static function ($message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });

            return [
                'status' => 'success',
                'error' => null,
                'response' => [
                    'mode' => 'mailer',
                    'provider' => (string) config('mail.default', 'smtp'),
                    'to' => $to,
                ],
                'external_ref' => 'mail_' . Str::uuid()->toString(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'response' => [
                    'mode' => 'mailer_error',
                    'to' => $to,
                ],
                'external_ref' => null,
            ];
        }
    }

    /**
     * @param array<string,mixed> $config
     * @param array<string,mixed> $context
     * @return array{status:string,error:?string,response:array<string,mixed>,external_ref:?string}
     */
    private function dispatchWebhookChannel(string $channel, object $lead, array $config, array $context): array
    {
        $target = $channel === 'sms'
            ? trim((string) ($lead->phone_e164 ?? ''))
            : trim((string) ($lead->whatsapp_e164 ?? ''));

        if ($target === '') {
            return [
                'status' => 'failed',
                'error' => "Contato sem destino para {$channel}.",
                'response' => ['mode' => 'validation'],
                'external_ref' => null,
            ];
        }

        $baseUrl = rtrim((string) config("automation_dispatch.{$channel}.webhook_url"), '/');
        if ($baseUrl === '') {
            return [
                'status' => 'failed',
                'error' => "Webhook {$channel} não configurado.",
                'response' => ['mode' => 'not_configured'],
                'external_ref' => null,
            ];
        }

        $messageTemplate = (string) ($config['message'] ?? config("automation_dispatch.{$channel}.default_message"));
        $message = $this->renderTemplate($messageTemplate, $lead);
        $timeout = max(2, min((int) config("automation_dispatch.{$channel}.timeout_seconds", 10), 30));
        $maxAttempts = max(1, (int) config("automation_dispatch.{$channel}.retry_attempts", 3));
        $backoffMs = $this->parseIntList(
            (string) config("automation_dispatch.{$channel}.retry_backoff_ms", '500,1500,3500'),
            [500, 1500, 3500]
        );
        $retryableStatuses = $this->parseIntList(
            (string) config("automation_dispatch.{$channel}.retryable_statuses", '408,409,425,429,500,502,503,504'),
            [408, 409, 425, 429, 500, 502, 503, 504]
        );
        $retryOnExceptions = (bool) config("automation_dispatch.{$channel}.retry_on_exceptions", true);
        $retryableExceptionPatterns = $this->parseStringList(
            (string) config(
                "automation_dispatch.{$channel}.retryable_exception_patterns",
                'timeout,timed out,connection refused,connection reset,temporarily unavailable,server has gone away'
            )
        );
        $idempotencyKey = trim((string) ($context['idempotency_key'] ?? ''));

        $payload = [
            'channel' => $channel,
            'to' => $target,
            'message' => $message,
            'meta' => [
                'lead_id' => (int) ($lead->id ?? 0),
                'lead_source_id' => (int) ($lead->lead_source_id ?? 0),
            ],
        ];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $request = Http::timeout($timeout);
                if ($idempotencyKey !== '') {
                    $request = $request->withHeaders([
                        'X-Idempotency-Key' => $idempotencyKey,
                    ]);
                }

                $response = $request->post($baseUrl, $payload);

                if ($response->successful()) {
                    $json = $response->json();
                    $externalRef = is_array($json) ? (string) ($json['id'] ?? $json['external_ref'] ?? '') : '';

                    return [
                        'status' => 'success',
                        'error' => null,
                        'response' => [
                            'mode' => 'webhook',
                            'provider' => 'webhook',
                            'status' => $response->status(),
                            'attempt' => $attempt,
                        ],
                        'external_ref' => $externalRef !== '' ? Str::limit($externalRef, 160, '') : 'hook_' . Str::uuid()->toString(),
                    ];
                }

                $shouldRetry = $attempt < $maxAttempts && $this->shouldRetryHttpStatus(
                    status: (int) $response->status(),
                    retryableStatuses: $retryableStatuses
                );
                if (!$shouldRetry) {
                    return [
                        'status' => 'failed',
                        'error' => "HTTP {$response->status()} no provider {$channel}.",
                        'response' => [
                            'mode' => 'webhook_error',
                            'provider' => 'webhook',
                            'status' => $response->status(),
                            'body' => Str::limit((string) $response->body(), 800, ''),
                            'attempt' => $attempt,
                        ],
                        'external_ref' => null,
                    ];
                }
            } catch (\Throwable $e) {
                $shouldRetryException = $attempt < $maxAttempts && $retryOnExceptions && $this->shouldRetryException(
                    exception: $e,
                    retryablePatterns: $retryableExceptionPatterns
                );
                if (!$shouldRetryException) {
                    return [
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'response' => [
                            'mode' => 'webhook_exception',
                            'provider' => 'webhook',
                            'attempt' => $attempt,
                        ],
                        'external_ref' => null,
                    ];
                }
            }

            $waitMs = $backoffMs[min($attempt - 1, count($backoffMs) - 1)] ?? 500;
            usleep(max(1, $waitMs) * 1000);
        }

        return [
            'status' => 'failed',
            'error' => "Falha inesperada no dispatch de {$channel}.",
            'response' => ['mode' => 'unknown'],
            'external_ref' => null,
        ];
    }

    private function renderTemplate(string $template, object $lead): string
    {
        $replacements = [
            '{nome}' => (string) ($lead->name ?? ''),
            '{email}' => (string) ($lead->email ?? ''),
            '{telefone}' => (string) ($lead->phone_e164 ?? ''),
            '{whatsapp}' => (string) ($lead->whatsapp_e164 ?? ''),
            '{id}' => (string) ((int) ($lead->id ?? 0)),
        ];

        return trim(strtr($template, $replacements));
    }

    /**
     * @param array<int,int> $fallback
     * @return array<int,int>
     */
    private function parseIntList(string $raw, array $fallback): array
    {
        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn (string $v): bool => $v !== '');
        $values = array_values(array_filter(array_map('intval', $parts), static fn (int $v): bool => $v > 0));
        return $values !== [] ? $values : $fallback;
    }

    /**
     * @return array<int,string>
     */
    private function parseStringList(string $raw): array
    {
        return array_values(array_filter(array_map(
            static fn (string $v): string => Str::lower(trim($v)),
            explode(',', $raw)
        ), static fn (string $v): bool => $v !== ''));
    }

    /**
     * @param array<int,int> $retryableStatuses
     */
    private function shouldRetryHttpStatus(int $status, array $retryableStatuses): bool
    {
        if (in_array($status, $retryableStatuses, true)) {
            return true;
        }

        // Retry on provider-side 5xx by default, even if status list is incomplete.
        return $status >= 500 && $status <= 599;
    }

    /**
     * @param array<int,string> $retryablePatterns
     */
    private function shouldRetryException(\Throwable $exception, array $retryablePatterns): bool
    {
        $message = Str::lower(trim($exception->getMessage()));
        if ($message === '') {
            return false;
        }

        foreach ($retryablePatterns as $pattern) {
            if ($pattern !== '' && Str::contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
