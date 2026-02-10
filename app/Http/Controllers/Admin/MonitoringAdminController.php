<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadSource;
use App\Services\LeadsVault\QueueHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class MonitoringAdminController extends Controller
{
    private function thresholdSource(string $envKey): string
    {
        $hasOverride = array_key_exists($envKey, $_ENV)
            || array_key_exists($envKey, $_SERVER)
            || getenv($envKey) !== false;

        return $hasOverride ? 'env override' : 'default config';
    }

    /**
     * @return array<string,mixed>
     */
    private function thresholds(): array
    {
        return [
            'db' => [
                'latency_warning_ms' => [
                    'value' => max(1, (int) config('monitoring.db.latency_warning_ms', 250)),
                    'source' => $this->thresholdSource('MONITORING_DB_LATENCY_WARNING_MS'),
                ],
                'latency_critical_ms' => [
                    'value' => max(1, (int) config('monitoring.db.latency_critical_ms', 800)),
                    'source' => $this->thresholdSource('MONITORING_DB_LATENCY_CRITICAL_MS'),
                ],
            ],
            'queue' => [
                'backlog_warning' => [
                    'value' => max(1, (int) config('monitoring.queue.backlog_warning', 120)),
                    'source' => $this->thresholdSource('MONITORING_QUEUE_BACKLOG_WARNING'),
                ],
                'backlog_critical' => [
                    'value' => max(1, (int) config('monitoring.queue.backlog_critical', 500)),
                    'source' => $this->thresholdSource('MONITORING_QUEUE_BACKLOG_CRITICAL'),
                ],
                'fail_15m_warning' => [
                    'value' => max(1, (int) config('monitoring.queue.fail_15m_warning', 3)),
                    'source' => $this->thresholdSource('MONITORING_QUEUE_FAIL_15M_WARNING'),
                ],
                'fail_15m_critical' => [
                    'value' => max(1, (int) config('monitoring.queue.fail_15m_critical', 10)),
                    'source' => $this->thresholdSource('MONITORING_QUEUE_FAIL_15M_CRITICAL'),
                ],
            ],
        ];
    }

    private function schedulerHealth(): array
    {
        $ttl = (int) config('monitoring.cache.scheduler_ttl', 15);

        return Cache::remember('monitoring:scheduler_health', $ttl, function () {
            $hasScheduleWork = false;
            $hasCronScheduleRun = false;
            $cronSample = null;

            $pgrepOutput = $this->runShell('pgrep -af "artisan schedule:work" 2>/dev/null');
            if (is_string($pgrepOutput) && trim($pgrepOutput) !== '') {
                $hasScheduleWork = true;
            }

            $crontabOutput = $this->runShell('crontab -l 2>/dev/null');
            if (is_string($crontabOutput) && trim($crontabOutput) !== '') {
                $lines = preg_split('/\r?\n/', trim($crontabOutput)) ?: [];
                foreach ($lines as $line) {
                    if (str_contains($line, 'artisan') && str_contains($line, 'schedule:run')) {
                        $hasCronScheduleRun = true;
                        $cronSample = trim((string) $line);
                        break;
                    }
                }
            }

            $lastMaintenanceAt = null;
            if (Schema::hasTable('jobs')) {
                $latestAvailableAt = DB::table('jobs')
                    ->where('queue', 'maintenance')
                    ->max('available_at');
                if ($latestAvailableAt) {
                    $lastMaintenanceAt = now()->setTimestamp((int) $latestAvailableAt)->toIso8601String();
                }
            }

            $status = ($hasScheduleWork || $hasCronScheduleRun) ? 'healthy' : 'warning';
            $mode = $hasScheduleWork ? 'schedule_work' : ($hasCronScheduleRun ? 'cron_schedule_run' : 'unknown');

            return [
                'status' => $status,
                'mode' => $mode,
                'schedule_work_running' => $hasScheduleWork,
                'cron_schedule_run_configured' => $hasCronScheduleRun,
                'cron_sample' => $cronSample,
                'last_maintenance_job_at' => $lastMaintenanceAt,
            ];
        });
    }

    private function dbHealth(array $thresholds): array
    {
        $latencyMs = null;
        $threadsConnected = null;
        $maxConnections = null;
        $slowQueries = null;
        $status = 'healthy';
        $message = 'Conexão com banco operacional.';

        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            if (DB::getDriverName() === 'mysql') {
                $statusRows = DB::select("SHOW STATUS WHERE Variable_name IN ('Threads_connected','Slow_queries')");
                foreach ($statusRows as $row) {
                    $name = (string) ($row->Variable_name ?? '');
                    $value = (int) ($row->Value ?? 0);
                    if ($name === 'Threads_connected') {
                        $threadsConnected = $value;
                    } elseif ($name === 'Slow_queries') {
                        $slowQueries = $value;
                    }
                }

                $varRows = DB::select("SHOW VARIABLES WHERE Variable_name='max_connections'");
                foreach ($varRows as $row) {
                    if ((string) ($row->Variable_name ?? '') === 'max_connections') {
                        $maxConnections = (int) ($row->Value ?? 0);
                    }
                }
            }

            $latencyWarning = (int) ($thresholds['db']['latency_warning_ms']['value'] ?? 250);
            $latencyCritical = (int) ($thresholds['db']['latency_critical_ms']['value'] ?? 800);
            if ($latencyMs !== null && $latencyMs >= $latencyCritical) {
                $status = 'critical';
                $message = 'Banco com latência crítica.';
            } elseif ($latencyMs !== null && $latencyMs >= $latencyWarning) {
                $status = 'warning';
                $message = 'Banco com latência elevada.';
            }
            if ($maxConnections && $threadsConnected !== null && $threadsConnected >= (int) floor($maxConnections * 0.85)) {
                $status = 'warning';
                $message = 'Banco próximo do limite de conexões.';
            }
        } catch (\Throwable $e) {
            $status = 'critical';
            $message = 'Falha de conexão com banco: ' . $e->getMessage();
        }

        return [
            'status' => $status,
            'message' => $message,
            'latency_ms' => $latencyMs,
            'threads_connected' => $threadsConnected,
            'max_connections' => $maxConnections,
            'slow_queries' => $slowQueries,
        ];
    }

    private function probeTcp(string $host, int $port, float $timeoutSeconds = 1.5): array
    {
        $errno = 0;
        $errstr = '';
        $start = microtime(true);
        $conn = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);
        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        if (is_resource($conn)) {
            fclose($conn);
            return [
                'status' => 'healthy',
                'latency_ms' => $latencyMs,
                'error' => null,
            ];
        }

        return [
            'status' => 'critical',
            'latency_ms' => $latencyMs,
            'error' => trim("{$errno} {$errstr}"),
        ];
    }

    private function externalServicesHealth(): array
    {
        $ttl = (int) config('monitoring.cache.external_ttl', 15);

        return Cache::remember('monitoring:external_services', $ttl, function () {
            $services = [];

            $smsUrl = trim((string) config('automation_dispatch.sms.webhook_url', ''));
            $services[] = $this->probeWebhookService('sms_webhook', $smsUrl);

            $whatsUrl = trim((string) config('automation_dispatch.whatsapp.webhook_url', ''));
            $services[] = $this->probeWebhookService('whatsapp_webhook', $whatsUrl);

            $mailDriver = (string) config('mail.default', 'smtp');
            $smtpHost = trim((string) config('mail.mailers.smtp.host', ''));
            $smtpPort = (int) config('mail.mailers.smtp.port', 587);
            if ($mailDriver === 'smtp' && $smtpHost !== '') {
                $probe = $this->probeTcp($smtpHost, max(1, $smtpPort), 1.5);
                $services[] = [
                    'service' => 'smtp',
                    'status' => $probe['status'],
                    'target' => "{$smtpHost}:{$smtpPort}",
                    'latency_ms' => $probe['latency_ms'],
                    'error' => $probe['error'],
                    'enabled' => true,
                ];
            } else {
                $services[] = [
                    'service' => 'smtp',
                    'status' => 'info',
                    'target' => $mailDriver !== '' ? $mailDriver : 'n/a',
                    'latency_ms' => null,
                    'error' => null,
                    'enabled' => false,
                ];
            }

            return $services;
        });
    }

    private function probeWebhookService(string $name, string $url): array
    {
        if ($url === '') {
            return [
                'service' => $name,
                'status' => 'info',
                'target' => 'não configurado',
                'latency_ms' => null,
                'error' => null,
                'enabled' => false,
            ];
        }

        $parts = parse_url($url) ?: [];
        $host = (string) ($parts['host'] ?? '');
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $port = (int) ($parts['port'] ?? ($scheme === 'http' ? 80 : 443));
        if ($host === '') {
            return [
                'service' => $name,
                'status' => 'critical',
                'target' => $url,
                'latency_ms' => null,
                'error' => 'URL inválida',
                'enabled' => true,
            ];
        }

        $probe = $this->probeTcp($host, $port, 1.5);
        return [
            'service' => $name,
            'status' => $probe['status'],
            'target' => "{$host}:{$port}",
            'latency_ms' => $probe['latency_ms'],
            'error' => $probe['error'],
            'enabled' => true,
        ];
    }

    private function queueThroughput(array $pendingByQueue, array $failedByQueue15m): array
    {
        $windowMinutes = 15;
        $importsCompleted = (int) LeadSource::query()
            ->whereIn('status', ['done', 'failed', 'cancelled'])
            ->where('updated_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        $normalizeCompleted = (int) LeadSource::query()
            ->where('status', 'done')
            ->where('updated_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        $extrasProcessed = 0;
        if (Schema::hasTable('automation_events')) {
            $extrasProcessed = (int) DB::table('automation_events')
                ->whereIn('status', ['success', 'failed', 'skipped'])
                ->where('updated_at', '>=', now()->subMinutes($windowMinutes))
                ->count();
        }

        $rows = [
            [
                'queue' => 'imports',
                'processed_15m' => $importsCompleted,
                'per_min' => round($importsCompleted / $windowMinutes, 2),
                'pending' => (int) ($pendingByQueue['imports'] ?? 0),
                'failed_15m' => (int) ($failedByQueue15m['imports'] ?? 0),
            ],
            [
                'queue' => 'normalize',
                'processed_15m' => $normalizeCompleted,
                'per_min' => round($normalizeCompleted / $windowMinutes, 2),
                'pending' => (int) ($pendingByQueue['normalize'] ?? 0),
                'failed_15m' => (int) ($failedByQueue15m['normalize'] ?? 0),
            ],
            [
                'queue' => 'extras',
                'processed_15m' => $extrasProcessed,
                'per_min' => round($extrasProcessed / $windowMinutes, 2),
                'pending' => (int) ($pendingByQueue['extras'] ?? 0),
                'failed_15m' => (int) ($failedByQueue15m['extras'] ?? 0),
            ],
        ];

        return [
            'window_minutes' => $windowMinutes,
            'rows' => $rows,
        ];
    }

    private function redisCacheHealth(): array
    {
        $ttl = (int) config('monitoring.cache.redis_ttl', 10);

        return Cache::remember('monitoring:redis_health', $ttl, function () {
            $status = 'info';
            $message = 'Cache/Redis não configurado.';
            $ping = null;
            $memoryUsed = null;
            $evictedKeys = null;
            $hitRate = null;
            $cacheDriver = (string) config('cache.default', 'file');
            $details = [];

            if (!in_array($cacheDriver, ['redis', 'memcached'], true) && !config('database.redis.default')) {
                return [
                    'status' => $status,
                    'message' => $message,
                    'cache_driver' => $cacheDriver,
                    'ping' => $ping,
                    'memory_used' => $memoryUsed,
                    'evicted_keys' => $evictedKeys,
                    'hit_rate' => $hitRate,
                    'details' => $details,
                ];
            }

            try {
                $conn = Redis::connection();
                $pong = $conn->ping();
                $ping = is_scalar($pong) ? strtolower((string) $pong) : 'ok';

                $infoMemory = $conn->command('INFO', ['memory']);
                $infoStats = $conn->command('INFO', ['stats']);

                $memory = $this->parseRedisInfo($infoMemory);
                $stats = $this->parseRedisInfo($infoStats);

                $memoryUsed = (string) ($memory['used_memory_human'] ?? ($memory['used_memory'] ?? '—'));
                $evictedKeys = isset($stats['evicted_keys']) ? (int) $stats['evicted_keys'] : null;
                $hits = isset($stats['keyspace_hits']) ? (int) $stats['keyspace_hits'] : 0;
                $misses = isset($stats['keyspace_misses']) ? (int) $stats['keyspace_misses'] : 0;
                $total = $hits + $misses;
                $hitRate = $total > 0 ? round(($hits / $total) * 100, 2) : null;

                $status = 'healthy';
                $message = 'Redis/cache operacional.';
                if ($evictedKeys !== null && $evictedKeys > 0) {
                    $status = 'warning';
                    $message = 'Redis com evictions detectados.';
                }
            } catch (\Throwable $e) {
                $status = 'critical';
                $message = 'Falha ao consultar Redis/cache.';
                $details[] = $e->getMessage();
            }

            return [
                'status' => $status,
                'message' => $message,
                'cache_driver' => $cacheDriver,
                'ping' => $ping,
                'memory_used' => $memoryUsed,
                'evicted_keys' => $evictedKeys,
                'hit_rate' => $hitRate,
                'details' => $details,
            ];
        });
    }

    /**
     * @return array<string,string>
     */
    private function parseRedisInfo(mixed $info): array
    {
        if (is_array($info)) {
            return array_map(fn ($v) => (string) $v, $info);
        }

        if (!is_string($info) || trim($info) === '') {
            return [];
        }

        $result = [];
        $lines = preg_split('/\r?\n/', trim($info)) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $result[trim($key)] = trim($value);
        }
        return $result;
    }

    private function diskStorageHealth(): array
    {
        $ttl = (int) config('monitoring.cache.disk_ttl', 30);

        return Cache::remember('monitoring:disk_health', $ttl, function () {
            $status = 'healthy';
            $message = 'Storage operacional.';
            $paths = [
                'storage_root' => storage_path(),
                'storage_logs' => storage_path('logs'),
                'storage_private' => storage_path('app/private'),
                'tmp' => '/tmp',
            ];

            $df = $this->readDiskFree($paths['storage_root']);
            $inode = $this->readDiskInode($paths['storage_root']);
            $sizes = [
                'storage_logs' => $this->readDirSizeKb($paths['storage_logs']),
                'storage_private' => $this->readDirSizeKb($paths['storage_private']),
                'tmp' => $this->readDirSizeKb($paths['tmp']),
            ];

            $usedPercent = (int) ($df['used_percent'] ?? 0);
            if ($usedPercent >= 95) {
                $status = 'critical';
                $message = 'Disco com uso crítico.';
            } elseif ($usedPercent >= 85) {
                $status = 'warning';
                $message = 'Disco com uso elevado.';
            }

            return [
                'status' => $status,
                'message' => $message,
                'disk' => $df,
                'inode' => $inode,
                'sizes_kb' => $sizes,
            ];
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function readDiskFree(string $path): array
    {
        $cmd = 'df -Pk ' . escapeshellarg($path) . ' 2>/dev/null | tail -1';
        $line = trim((string) ($this->runShell($cmd) ?? ''));
        if ($line === '') {
            return [];
        }
        $parts = preg_split('/\s+/', $line) ?: [];
        if (count($parts) < 6) {
            return [];
        }
        return [
            'filesystem' => (string) $parts[0],
            'total_kb' => (int) ($parts[1] ?? 0),
            'used_kb' => (int) ($parts[2] ?? 0),
            'available_kb' => (int) ($parts[3] ?? 0),
            'used_percent' => (int) str_replace('%', '', (string) ($parts[4] ?? '0')),
            'mount' => (string) ($parts[5] ?? ''),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function readDiskInode(string $path): array
    {
        $cmd = 'df -Pi ' . escapeshellarg($path) . ' 2>/dev/null | tail -1';
        $line = trim((string) ($this->runShell($cmd) ?? ''));
        if ($line === '') {
            return [];
        }
        $parts = preg_split('/\s+/', $line) ?: [];
        if (count($parts) < 6) {
            return [];
        }
        return [
            'total' => (int) ($parts[1] ?? 0),
            'used' => (int) ($parts[2] ?? 0),
            'available' => (int) ($parts[3] ?? 0),
            'used_percent' => (int) str_replace('%', '', (string) ($parts[4] ?? '0')),
        ];
    }

    private function readDirSizeKb(string $path): ?int
    {
        $cmd = 'du -sk ' . escapeshellarg($path) . ' 2>/dev/null | cut -f1';
        $raw = trim((string) ($this->runShell($cmd) ?? ''));
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        return (int) $raw;
    }

    private function queueDelayRetries(array $pendingByQueue): array
    {
        $rows = [];
        if (Schema::hasTable('jobs')) {
            $nowTs = now()->timestamp;

            $aggregates = DB::table('jobs')
                ->select([
                    'queue',
                    DB::raw('COUNT(*) as pending'),
                    DB::raw("AVG(GREATEST({$nowTs} - available_at, 0)) as avg_wait_seconds"),
                    DB::raw('MAX(attempts) as max_attempts'),
                    DB::raw('SUM(CASE WHEN attempts > 1 THEN 1 ELSE 0 END) as retrying'),
                ])
                ->groupBy('queue')
                ->orderBy('queue')
                ->get();

            foreach ($aggregates as $agg) {
                $queueRaw = trim((string) ($agg->queue ?? ''));
                $rows[] = [
                    'queue' => $queueRaw !== '' ? $queueRaw : 'default',
                    'pending' => (int) $agg->pending,
                    'avg_wait_seconds' => round((float) $agg->avg_wait_seconds, 2),
                    'max_attempts' => (int) $agg->max_attempts,
                    'retrying' => (int) $agg->retrying,
                ];
            }
        }

        $globalAvg = 0.0;
        $retryingTotal = 0;
        $pendingTotal = 0;
        if ($rows) {
            $globalAvg = round(array_sum(array_map(fn ($r) => (float) ($r['avg_wait_seconds'] ?? 0), $rows)) / count($rows), 2);
            $retryingTotal = array_sum(array_map(fn ($r) => (int) ($r['retrying'] ?? 0), $rows));
            $pendingTotal = array_sum(array_map(fn ($r) => (int) ($r['pending'] ?? 0), $rows));
        }

        return [
            'rows' => $rows,
            'avg_wait_seconds' => $globalAvg,
            'retrying_total' => $retryingTotal,
            'pending_total' => $pendingTotal ?: array_sum($pendingByQueue),
        ];
    }

    /**
     * @return array<string,string>
     */
    private function queueProgramMap(): array
    {
        $queues = (array) config('monitoring.queues', []);
        $map = [];
        foreach ($queues as $queue => $meta) {
            $map[$queue] = (string) ($meta['program'] ?? '');
        }
        return $map;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function logIncident(Request $request, string $action, ?string $queueName, string $outcome, string $message, array $context = []): void
    {
        if (!Schema::hasTable('admin_monitoring_incidents')) {
            return;
        }

        $tenantUuid = app()->bound('tenant_uuid') ? (string) app('tenant_uuid') : null;
        DB::table('admin_monitoring_incidents')->insert([
            'user_id' => $request->user()?->id,
            'tenant_uuid' => $tenantUuid ?: null,
            'action' => $action,
            'queue_name' => $queueName,
            'outcome' => $outcome,
            'message' => $message,
            'context_json' => $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
        ]);
    }

    public function index()
    {
        return view('admin.monitoring.index');
    }

    public function health(Request $request, QueueHealthService $queueHealth): JsonResponse
    {
        $thresholds = $this->thresholds();
        $snapshot = $queueHealth->snapshot();
        $managedQueues = array_keys($this->queueProgramMap());
        $schedulerHealth = $this->schedulerHealth();
        $dbHealth = $this->dbHealth($thresholds);
        $redisHealth = $this->redisCacheHealth();

        $pendingByQueue = [];
        if (Schema::hasTable('jobs')) {
            $pendingByQueue = DB::table('jobs')
                ->select('queue', DB::raw('COUNT(*) as total'))
                ->groupBy('queue')
                ->pluck('total', 'queue')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        $failedByQueue24h = [];
        $failedByQueue15m = [];
        if (Schema::hasTable('failed_jobs')) {
            $failedByQueue24h = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->select('queue', DB::raw('COUNT(*) as total'))
                ->groupBy('queue')
                ->pluck('total', 'queue')
                ->map(fn ($v) => (int) $v)
                ->all();

            $failedByQueue15m = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subMinutes(15))
                ->select('queue', DB::raw('COUNT(*) as total'))
                ->groupBy('queue')
                ->pluck('total', 'queue')
                ->map(fn ($v) => (int) $v)
                ->all();
        }
        $queueThroughput = $this->queueThroughput($pendingByQueue, $failedByQueue15m);
        $queueDelayRetries = $this->queueDelayRetries($pendingByQueue);
        $diskHealth = $this->diskStorageHealth();
        $externalServices = $this->externalServicesHealth();

        $runningImports = LeadSource::query()
            ->whereIn('status', ['queued', 'uploading', 'importing', 'normalizing'])
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'original_name', 'status', 'progress_percent', 'inserted_rows', 'updated_at']);

        $stalledImports = LeadSource::query()
            ->whereIn('status', ['queued', 'uploading', 'importing', 'normalizing'])
            ->where('updated_at', '<', now()->subMinutes(5))
            ->orderBy('updated_at')
            ->limit(20)
            ->get(['id', 'original_name', 'status', 'progress_percent', 'inserted_rows', 'updated_at']);

        $activeUsers = 0;
        if (Schema::hasTable('sessions')) {
            $activeUsers = (int) DB::table('sessions')
                ->whereNotNull('user_id')
                ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
                ->distinct('user_id')
                ->count('user_id');
        }

        $instructions = [];
        $queueRecommendations = [];
        $workers = (array) ($snapshot['workers'] ?? []);
        $allQueues = array_values(array_unique(array_merge(
            array_keys($workers),
            array_keys($pendingByQueue),
            array_keys($failedByQueue24h),
            array_keys($failedByQueue15m),
        )));

        foreach ($allQueues as $queue) {
            $row = (array) ($workers[$queue] ?? []);
            $states = collect((array) ($row['states'] ?? []))
                ->map(fn ($s) => strtoupper((string) $s))
                ->values();
            $running = (int) ($row['running'] ?? 0);
            $expected = (int) ($row['expected'] ?? 0);
            $pending = (int) ($pendingByQueue[$queue] ?? 0);
            $failed15 = (int) ($failedByQueue15m[$queue] ?? 0);
            $failed24 = (int) ($failedByQueue24h[$queue] ?? 0);
            $isManaged = in_array($queue, $managedQueues, true);

            $hasStoppedState = $states->contains('STOPPED') || $states->contains('FATAL') || $states->contains('EXITED') || $states->contains('BACKOFF');
            $hasStoppingState = $states->contains('STOPPING');
            $degradedWorkers = $expected > 0 && $running < $expected;
            $queuePressure = $pending >= (int) ($thresholds['queue']['backlog_warning']['value'] ?? 120)
                || $failed15 >= (int) ($thresholds['queue']['fail_15m_warning']['value'] ?? 3);
            $historicOnly = !$hasStoppedState && !$hasStoppingState && !$degradedWorkers && !$queuePressure && $failed24 > 0;

            $recommendedAction = 'none';
            $reason = 'Operação estável.';

            if ($isManaged && ($hasStoppedState || $hasStoppingState || $degradedWorkers || $queuePressure)) {
                $recommendedAction = 'recover';
                $reason = 'Worker degradado/parado ou pressão de fila.';
            } elseif ($historicOnly || $failed15 > 0 || $failed24 > 0) {
                $recommendedAction = 'view_failures';
                $reason = 'Falhas detectadas; revisar antes de recuperar.';
            } elseif (!$isManaged && ($pending > 0 || $failed15 > 0 || $failed24 > 0)) {
                $recommendedAction = 'inspect';
                $reason = 'Fila não gerenciada por supervisor web.';
            }

            $queueRecommendations[$queue] = [
                'queue' => $queue,
                'managed' => $isManaged,
                'recommended_action' => $recommendedAction,
                'reason' => $reason,
                'pending' => $pending,
                'failed_15m' => $failed15,
                'failed_24h' => $failed24,
            ];

            if ($states->contains('STOPPED') || $states->contains('FATAL') || $states->contains('EXITED') || $states->contains('BACKOFF')) {
                $instructions[] = "Fila {$queue}: worker parado. Execute queue:restart e valide supervisorctl status.";
                continue;
            }
            if ($states->contains('STOPPING')) {
                $instructions[] = "Fila {$queue}: worker em STOPPING. Aguardar até 60s; se persistir, reiniciar programa no supervisor.";
                continue;
            }
            if ((int) ($row['running'] ?? 0) < (int) ($row['expected'] ?? 0)) {
                $instructions[] = "Fila {$queue}: capacidade degradada ({$row['running']}/{$row['expected']}). Verificar backlog e processos.";
            }
        }

        $actionFilter = trim((string) $request->query('incident_action', ''));
        $queueFilter = trim((string) $request->query('incident_queue', ''));
        $outcomeFilter = trim((string) $request->query('incident_outcome', ''));
        $incidentPage = max(1, (int) $request->integer('incident_page', 1));
        $incidentPerPage = max(5, min((int) $request->integer('incident_per_page', 10), 50));

        $incidentHistory = [];
        $incidentTotal = 0;
        if (Schema::hasTable('admin_monitoring_incidents')) {
            $hasAckAt = Schema::hasColumn('admin_monitoring_incidents', 'acknowledged_at');
            $hasAckBy = Schema::hasColumn('admin_monitoring_incidents', 'acknowledged_by_user_id');
            $hasAckComment = Schema::hasColumn('admin_monitoring_incidents', 'ack_comment');

            $incidentQuery = DB::table('admin_monitoring_incidents as i')
                ->leftJoin('users as u', 'u.id', '=', 'i.user_id')
                ->orderByDesc('i.id');
            if ($hasAckBy) {
                $incidentQuery->leftJoin('users as ua', 'ua.id', '=', 'i.acknowledged_by_user_id');
            }

            if ($actionFilter !== '') {
                $incidentQuery->where('i.action', $actionFilter);
            }
            if ($queueFilter !== '') {
                $incidentQuery->where('i.queue_name', $queueFilter);
            }
            if ($outcomeFilter !== '') {
                $incidentQuery->where('i.outcome', $outcomeFilter);
            }

            $countQuery = clone $incidentQuery;
            $incidentTotal = (int) $countQuery->count('i.id');
            $offset = ($incidentPage - 1) * $incidentPerPage;

            $selects = [
                'i.id',
                'i.action',
                'i.queue_name',
                'i.outcome',
                'i.message',
                'i.created_at',
                DB::raw('COALESCE(u.name, CONCAT("user#", i.user_id)) as actor_name'),
            ];
            if ($hasAckAt) {
                $selects[] = 'i.acknowledged_at';
            } else {
                $selects[] = DB::raw('NULL as acknowledged_at');
            }
            if ($hasAckComment) {
                $selects[] = 'i.ack_comment';
            } else {
                $selects[] = DB::raw('NULL as ack_comment');
            }
            if ($hasAckBy) {
                $selects[] = DB::raw('COALESCE(ua.name, CONCAT("user#", i.acknowledged_by_user_id)) as ack_actor_name');
            } else {
                $selects[] = DB::raw('NULL as ack_actor_name');
            }

            $incidentHistory = $incidentQuery
                ->offset($offset)
                ->limit($incidentPerPage)
                ->get($selects);
        }

        return response()->json([
            'ok' => true,
            'queue_health' => $snapshot,
            'thresholds' => $thresholds,
            'scheduler_health' => $schedulerHealth,
            'db_health' => $dbHealth,
            'redis_health' => $redisHealth,
            'disk_health' => $diskHealth,
            'external_services' => $externalServices,
            'queue_throughput' => $queueThroughput,
            'queue_delay_retries' => $queueDelayRetries,
            'managed_queues' => $managedQueues,
            'pending_by_queue' => $pendingByQueue,
            'failed_by_queue_24h' => $failedByQueue24h,
            'failed_by_queue_15m' => $failedByQueue15m,
            'queue_recommendations' => $queueRecommendations,
            'running_imports' => $runningImports,
            'stalled_imports' => $stalledImports,
            'active_users_5m' => $activeUsers,
            'operational_instructions' => array_values(array_unique($instructions)),
            'incident_history' => $incidentHistory,
            'incident_filters' => [
                'incident_action' => $actionFilter,
                'incident_queue' => $queueFilter,
                'incident_outcome' => $outcomeFilter,
            ],
            'incident_pagination' => [
                'page' => $incidentPage,
                'per_page' => $incidentPerPage,
                'total' => $incidentTotal,
                'last_page' => max(1, (int) ceil($incidentTotal / max(1, $incidentPerPage))),
            ],
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function exportIncidentsCsv(Request $request)
    {
        abort_unless(Schema::hasTable('admin_monitoring_incidents'), 404, 'Histórico de incidentes não disponível.');

        $actionFilter = trim((string) $request->query('incident_action', ''));
        $queueFilter = trim((string) $request->query('incident_queue', ''));
        $outcomeFilter = trim((string) $request->query('incident_outcome', ''));

        $query = DB::table('admin_monitoring_incidents as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.user_id')
            ->orderByDesc('i.id');
        $hasAckAt = Schema::hasColumn('admin_monitoring_incidents', 'acknowledged_at');
        $hasAckBy = Schema::hasColumn('admin_monitoring_incidents', 'acknowledged_by_user_id');
        $hasAckComment = Schema::hasColumn('admin_monitoring_incidents', 'ack_comment');
        if ($hasAckBy) {
            $query->leftJoin('users as ua', 'ua.id', '=', 'i.acknowledged_by_user_id');
        }

        if ($actionFilter !== '') {
            $query->where('i.action', $actionFilter);
        }
        if ($queueFilter !== '') {
            $query->where('i.queue_name', $queueFilter);
        }
        if ($outcomeFilter !== '') {
            $query->where('i.outcome', $outcomeFilter);
        }

        $selects = [
            'i.id',
            'i.created_at',
            'i.action',
            'i.queue_name',
            'i.outcome',
            DB::raw('COALESCE(u.name, CONCAT("user#", i.user_id)) as actor_name'),
            'i.message',
        ];
        $selects[] = $hasAckAt ? 'i.acknowledged_at' : DB::raw('NULL as acknowledged_at');
        $selects[] = $hasAckComment ? 'i.ack_comment' : DB::raw('NULL as ack_comment');
        $selects[] = $hasAckBy
            ? DB::raw('COALESCE(ua.name, CONCAT("user#", i.acknowledged_by_user_id)) as ack_actor_name')
            : DB::raw('NULL as ack_actor_name');

        $rows = $query->limit(5000)->get($selects);

        $filename = 'monitoring-incidents-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, [
                'id',
                'created_at',
                'action',
                'queue_name',
                'outcome',
                'actor_name',
                'message',
                'acknowledged_at',
                'ack_actor_name',
                'ack_comment',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    (string) ($row->id ?? ''),
                    (string) ($row->created_at ?? ''),
                    (string) ($row->action ?? ''),
                    (string) ($row->queue_name ?? ''),
                    (string) ($row->outcome ?? ''),
                    (string) ($row->actor_name ?? ''),
                    (string) ($row->message ?? ''),
                    (string) ($row->acknowledged_at ?? ''),
                    (string) ($row->ack_actor_name ?? ''),
                    (string) ($row->ack_comment ?? ''),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function restartQueues(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Apenas superadmin pode reiniciar workers.');

        Artisan::call('queue:restart');
        $this->logIncident($request, 'queue_restart_all', 'all', 'ok', 'queue:restart solicitado');

        return response()->json([
            'ok' => true,
            'message' => 'Sinal de reinício enviado para os workers.',
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function recoverQueue(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Apenas superadmin pode recuperar filas.');

        $allowedQueues = implode(',', array_keys((array) config('monitoring.queues', [])));
        $data = $request->validate([
            'queue' => ['required', 'string', "in:{$allowedQueues}"],
            'confirmation_text' => ['required', 'string', 'max:80'],
        ]);

        $queue = (string) $data['queue'];
        $expectedConfirmation = 'RECUPERAR ' . strtoupper($queue);
        if (strtoupper(trim((string) $data['confirmation_text'])) !== $expectedConfirmation) {
            $this->logIncident($request, 'queue_recover', $queue, 'blocked', 'Confirmação inválida', [
                'expected' => $expectedConfirmation,
            ]);
            return response()->json([
                'ok' => false,
                'message' => "Confirmação inválida. Digite exatamente: {$expectedConfirmation}",
            ], 422);
        }

        $program = $this->queueProgramMap()[$queue] ?? null;
        if (!$program) {
            $this->logIncident($request, 'queue_recover', $queue, 'error', 'Fila inválida');
            return response()->json([
                'ok' => false,
                'message' => 'Fila inválida.',
            ], 422);
        }

        $command = 'supervisorctl restart ' . escapeshellarg($program) . ' 2>&1';
        $output = $this->runShell($command);
        $outputText = is_string($output) ? trim($output) : '';
        $normalized = strtolower($outputText);

        $supervisorOk = $outputText !== ''
            && !str_contains($normalized, 'permission denied')
            && !str_contains($normalized, 'error')
            && !str_contains($normalized, 'no such file')
            && !str_contains($normalized, 'not found');

        $mode = 'supervisor';
        if (!$supervisorOk) {
            Artisan::call('queue:restart');
            $mode = 'fallback_queue_restart';
        }
        $this->logIncident(
            $request,
            'queue_recover',
            $queue,
            $mode === 'supervisor' ? 'ok' : 'fallback',
            $mode === 'supervisor' ? 'Recuperação via supervisorctl' : 'Fallback para queue:restart',
            ['mode' => $mode, 'supervisor_output' => $outputText]
        );

        return response()->json([
            'ok' => true,
            'queue' => $queue,
            'mode' => $mode,
            'message' => $mode === 'supervisor'
                ? "Recuperação da fila {$queue} solicitada com sucesso."
                : "Fila {$queue}: supervisor indisponível via web. Aplicado queue:restart global; valide supervisor manualmente.",
            'supervisor_output' => $outputText,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function acknowledgeIncident(Request $request, int $id): JsonResponse
    {
        abort_unless(Schema::hasTable('admin_monitoring_incidents'), 404, 'Histórico de incidentes não disponível.');
        abort_unless(
            Schema::hasColumn('admin_monitoring_incidents', 'acknowledged_at')
            && Schema::hasColumn('admin_monitoring_incidents', 'acknowledged_by_user_id'),
            409,
            'ACK de incidente indisponível até aplicar migrations.'
        );

        $user = $request->user();
        abort_unless($user !== null, 401, 'Usuário não autenticado.');

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:255'],
        ]);

        $incident = DB::table('admin_monitoring_incidents')
            ->where('id', $id)
            ->first(['id', 'action', 'queue_name', 'outcome', 'acknowledged_at', 'acknowledged_by_user_id']);

        if (!$incident) {
            return response()->json([
                'ok' => false,
                'message' => 'Incidente não encontrado.',
            ], 404);
        }

        if ($incident->acknowledged_at !== null) {
            return response()->json([
                'ok' => true,
                'message' => 'Incidente já estava reconhecido.',
                'acknowledged_at' => (string) $incident->acknowledged_at,
                'acknowledged_by_user_id' => (int) ($incident->acknowledged_by_user_id ?? 0),
            ]);
        }

        DB::table('admin_monitoring_incidents')
            ->where('id', $id)
            ->update([
                'acknowledged_at' => now(),
                'acknowledged_by_user_id' => (int) $user->id,
                'ack_comment' => isset($data['comment']) ? trim((string) $data['comment']) : null,
            ]);

        $this->logIncident(
            $request,
            'incident_ack',
            $incident->queue_name ? (string) $incident->queue_name : null,
            'ok',
            'Incidente reconhecido por operador',
            [
                'incident_id' => $id,
                'incident_action' => (string) ($incident->action ?? ''),
                'incident_outcome' => (string) ($incident->outcome ?? ''),
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => 'Incidente reconhecido com sucesso.',
            'incident_id' => $id,
            'acknowledged_at' => now()->toIso8601String(),
            'acknowledged_by_user_id' => (int) $user->id,
        ]);
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
