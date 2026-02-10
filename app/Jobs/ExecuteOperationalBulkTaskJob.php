<?php

namespace App\Jobs;

use App\Services\LeadsVault\OperationalBulkTaskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteOperationalBulkTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(
        public int $taskId,
        public string $tenantUuid
    ) {
        $this->onQueue('automation');
    }

    public function handle(OperationalBulkTaskService $service): void
    {
        $service->executeTask($this->taskId, $this->tenantUuid);
    }
}
