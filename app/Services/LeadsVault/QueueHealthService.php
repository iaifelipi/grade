<?php

namespace App\Services\LeadsVault;

class QueueHealthService
{
    /**
     * @return array<string,mixed>
     */
    public function snapshot(): array
    {
        $isCronWorkerMode = (string) config('monitoring.queue.worker_mode', 'process') === 'cron';

        $queues = (array) config('monitoring.queues', [
            'imports'   => ['expected' => 2, 'program' => 'grade-imports:*'],
            'normalize' => ['expected' => 2, 'program' => 'grade-normalize:*'],
            'extras'    => ['expected' => 1, 'program' => 'grade-extras:*'],
        ]);

        $supervisorStatuses = $this->readSupervisorStatuses();
        $workers = [];
        $severity = 'healthy';
        $messages = [];

        foreach ($queues as $queue => $meta) {
            $expected = $isCronWorkerMode ? 0 : (int) $meta['expected'];
            $programPrefix = rtrim((string) ($meta['program'] ?? ''), '*');

            $statusRows = [];
            if ($supervisorStatuses !== null) {
                foreach ($supervisorStatuses as $row) {
                    if (str_starts_with($row['name'], $programPrefix)) {
                        $statusRows[] = $row['status'];
                    }
                }
            }

            if ($supervisorStatuses === null) {
                $running = $this->countQueueWorkersByProcess($queue);
                if ($isCronWorkerMode && $running === 0) {
                    $state = 'IDLE';
                } else {
                    $state = $running >= $expected ? 'RUNNING' : ($running > 0 ? 'DEGRADED' : 'STOPPED');
                }
                $statusRows = array_fill(0, max(1, $running), $state);
            }

            $runningCount = count(array_filter($statusRows, fn (string $s): bool => $s === 'RUNNING'));
            $stoppingCount = count(array_filter($statusRows, fn (string $s): bool => $s === 'STOPPING'));
            $stoppedCount = count(array_filter($statusRows, fn (string $s): bool => in_array($s, ['STOPPED', 'FATAL', 'EXITED', 'BACKOFF'], true)));

            $queueStatus = 'healthy';
            if ($stoppedCount > 0 || (!$isCronWorkerMode && $runningCount === 0)) {
                $queueStatus = 'critical';
            } elseif ($stoppingCount > 0 || (!$isCronWorkerMode && $runningCount < $expected)) {
                $queueStatus = 'warning';
            }

            if ($queueStatus === 'critical') {
                $severity = 'critical';
                $messages[] = "Fila {$queue}: worker parado";
            } elseif ($queueStatus === 'warning' && $severity !== 'critical') {
                $severity = 'warning';
                $messages[] = "Fila {$queue}: worker degradado";
            }

            $workers[$queue] = [
                'status' => $queueStatus,
                'expected' => $expected,
                'running' => $runningCount,
                'states' => $statusRows,
            ];
        }

        $message = match ($severity) {
            'critical' => 'Workers de fila indisponíveis. Novos uploads podem ficar em fila até recuperar.',
            'warning' => 'Workers de fila em recuperação. Pode haver atraso no processamento.',
            default => 'Workers operacionais.',
        };

        return [
            'overall' => $severity,
            'message' => $message,
            'details' => array_values(array_unique($messages)),
            'workers' => $workers,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<int,array{name:string,status:string}>|null
     */
    private function readSupervisorStatuses(): ?array
    {
        $queues = (array) config('monitoring.queues', []);
        $programs = [];
        foreach ($queues as $meta) {
            $program = (string) ($meta['program'] ?? '');
            if ($program !== '') {
                $programs[] = escapeshellarg($program);
            }
        }
        if ($programs === []) {
            return null;
        }
        $output = $this->runShell('supervisorctl status ' . implode(' ', $programs) . ' 2>&1');
        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        if (str_contains(strtolower($output), 'permission denied') || str_contains(strtolower($output), 'no such file')) {
            return null;
        }

        $rows = [];
        foreach (preg_split('/\r?\n/', trim($output)) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^(?<name>\S+)\s+(?<status>[A-Z]+)/', $line, $m)) {
                $rows[] = [
                    'name' => (string) $m['name'],
                    'status' => strtoupper((string) $m['status']),
                ];
            }
        }

        return $rows ?: null;
    }

    private function countQueueWorkersByProcess(string $queue): int
    {
        $queue = preg_replace('/[^a-z0-9_-]/i', '', $queue) ?: '';
        if ($queue === '') {
            return 0;
        }

        $output = $this->runShell('pgrep -af "artisan queue:work database --queue=' . $queue . '" 2>/dev/null');
        if (!is_string($output) || trim($output) === '') {
            return 0;
        }

        $count = 0;
        foreach (preg_split('/\r?\n/', trim($output)) as $line) {
            if (trim((string) $line) !== '') {
                $count++;
            }
        }

        return $count;
    }

    private function runShell(string $command): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $output = @shell_exec($command);
        return is_string($output) ? $output : null;
    }
}
