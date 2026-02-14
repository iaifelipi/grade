<?php

namespace App\Jobs;

use App\Services\Security\SecurityRiskEvaluatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SecurityEvaluateAccessRiskJob implements ShouldQueue
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
        $window = (int) config('security_monitoring.risk.window_minutes', 15);
        /** @var SecurityRiskEvaluatorService $svc */
        $svc = app(SecurityRiskEvaluatorService::class);
        $result = $svc->evaluate($window);

        Log::info('security evaluate risk', [
            'execution_id' => $this->executionId,
            'window_minutes' => $window,
            'ok' => (bool) ($result['ok'] ?? false),
            'incidents_upserted' => (int) ($result['incidents_upserted'] ?? 0),
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
        Log::warning('security evaluate risk job failed', [
            'execution_id' => $this->executionId,
            'error' => $e->getMessage(),
        ]);
    }
}
