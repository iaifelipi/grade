<?php

namespace App\Jobs;

use App\Services\Security\CloudflareSecurityService;
use App\Services\Security\SecurityAccessEventWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SecurityIngestCloudflareEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public string $executionId;

    public function __construct()
    {
        $this->onQueue((string) config('security_monitoring.queue.name', 'maintenance'));
        $this->executionId = (string) Str::uuid();
    }

    public function handle(): void
    {
        $minutes = (int) config('security_monitoring.risk.window_minutes', 15);
        $limit = (int) config('security_monitoring.cloudflare.ingest_limit', 250);

        /** @var CloudflareSecurityService $cf */
        $cf = app(CloudflareSecurityService::class);
        /** @var SecurityAccessEventWriter $writer */
        $writer = app(SecurityAccessEventWriter::class);

        $result = $cf->ingestFirewallEvents($writer, $minutes, $limit);

        Log::info('security cloudflare ingest', [
            'execution_id' => $this->executionId,
            'enabled' => $cf->isEnabled(),
            'ok' => (bool) ($result['ok'] ?? false),
            'created' => (int) ($result['created'] ?? 0),
            'message' => (string) ($result['message'] ?? ''),
        ]);
    }

    public static function dispatchAsync(): void
    {
        $pending = self::dispatch();
        if ((string) config('queue.default') === 'sync') {
            $pending->afterResponse();
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('security cloudflare ingest job failed', [
            'execution_id' => $this->executionId,
            'error' => $e->getMessage(),
        ]);
    }
}
