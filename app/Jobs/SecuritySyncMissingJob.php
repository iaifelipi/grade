<?php

namespace App\Jobs;

use App\Services\SecuritySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SecuritySyncMissingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int,int> */
    public array $backoff = [15, 60, 180];

    public string $executionId;

    public function __construct()
    {
        $this->onQueue('maintenance');
        $this->executionId = (string) Str::uuid();
    }

    public function handle(SecuritySyncService $service): void
    {
        $attempt = $this->attempts();
        $service->syncMissingCopies($this->executionId, $attempt, $this->tries);
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
        Log::warning('security sync job failed', [
            'execution_id' => $this->executionId,
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
            'error' => $e->getMessage(),
        ]);
    }
}
