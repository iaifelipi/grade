<?php

namespace App\Jobs;

use App\Services\LeadsVault\AutomationExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteAutomationRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;

    public function __construct(
        public int $runId,
        public string $tenantUuid
    ) {
        $this->tries = max(1, (int) config('automation_dispatch.run.job_tries', 3));
        $this->onQueue('automation');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        $raw = (string) config('automation_dispatch.run.job_backoff_seconds', '10,30,90');
        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn (string $v): bool => $v !== '');
        $seconds = array_values(array_filter(array_map('intval', $parts), static fn (int $v): bool => $v > 0));

        return $seconds !== [] ? $seconds : [10, 30, 90];
    }

    public function handle(AutomationExecutionService $service): void
    {
        $service->executeRun($this->runId, $this->tenantUuid);
    }
}
