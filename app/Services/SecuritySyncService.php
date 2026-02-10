<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SecuritySyncService
{
    public function syncMissingCopies(?string $executionId = null, ?int $attempt = null, ?int $maxTries = null): void
    {
        $executionId = trim((string) $executionId);
        if ($executionId === '') {
            $executionId = uniqid('security_sync_', true);
        }

        $logContext = [
            'execution_id' => $executionId,
            'attempt' => $attempt,
            'max_tries' => $maxTries,
        ];

        $script = base_path('scripts/grade-security-sync-missing.sh');
        if (!is_file($script) || !is_executable($script)) {
            Log::warning('security sync script missing or not executable', [
                'script' => $script,
            ] + $logContext);
            return;
        }

        $startedAt = microtime(true);
        Log::info('security sync started', [
            'script' => $script,
        ] + $logContext);

        try {
            $process = new Process([$script], base_path());
            $process->setTimeout(30);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::warning('security sync script failed', [
                    'exit_code' => $process->getExitCode(),
                    'stderr' => trim($process->getErrorOutput()),
                ] + $logContext);
                return;
            }

            Log::info('security sync finished', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ] + $logContext);
        } catch (\Throwable $e) {
            Log::warning('security sync script execution error', [
                'message' => $e->getMessage(),
            ] + $logContext);
        }
    }
}
