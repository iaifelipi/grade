<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SecurityEvaluateAccessRiskJob;
use App\Jobs\SecurityIngestCloudflareEventsJob;
use App\Services\Security\CloudflareSecurityService;
use App\Services\Security\SecurityAccessEventWriter;
use App\Services\Security\SecurityRiskEvaluatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityAccessAdminController extends Controller
{
    public function index()
    {
        return view('admin.security.index');
    }

    public function health(): JsonResponse
    {
        $windowMinutes = max(1, (int) config('security_monitoring.risk.window_minutes', 15));

        $eventsQuery = Schema::hasTable('security_access_events')
            ? DB::table('security_access_events')
            : null;
        $incidentsQuery = Schema::hasTable('security_access_incidents')
            ? DB::table('security_access_incidents')
            : null;

        $eventsWindow = $eventsQuery
            ? (clone $eventsQuery)->where('occurred_at', '>=', now()->subMinutes($windowMinutes))
            : null;

        $eventsTotal = $eventsWindow ? (clone $eventsWindow)->count() : 0;
        $failedLogins = $eventsWindow
            ? (clone $eventsWindow)->where('event_type', 'login_failed')->count()
            : 0;
        $blockedIps24h = Schema::hasTable('security_access_actions')
            ? DB::table('security_access_actions')
                ->whereIn('action', ['block_ip', 'challenge_ip'])
                ->where('result', 'success')
                ->where('created_at', '>=', now()->subDay())
                ->distinct('target_value')
                ->count('target_value')
            : 0;
        $openIncidents = $incidentsQuery
            ? (clone $incidentsQuery)->where('status', 'open')->count()
            : 0;

        $topIps = $eventsWindow
            ? (clone $eventsWindow)
                ->select('ip_address', DB::raw('COUNT(*) as total'))
                ->whereNotNull('ip_address')
                ->groupBy('ip_address')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'ip' => (string) $row->ip_address,
                    'total' => (int) $row->total,
                ])
                ->values()
                ->all()
            : [];

        $recentIncidents = $incidentsQuery
            ? (clone $incidentsQuery)
                ->orderByDesc('last_seen_at')
                ->limit(15)
                ->get(['id', 'level', 'title', 'status', 'event_count', 'last_seen_at', 'acknowledged_at'])
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'level' => (string) ($row->level ?? 'info'),
                    'title' => (string) ($row->title ?? '-'),
                    'status' => (string) ($row->status ?? 'open'),
                    'event_count' => (int) ($row->event_count ?? 0),
                    'last_seen_at' => optional($row->last_seen_at)?->toDateTimeString(),
                    'acknowledged_at' => optional($row->acknowledged_at)?->toDateTimeString(),
                ])
                ->values()
                ->all()
            : [];

        $cloudflareEnabled = filled((string) config('security_monitoring.cloudflare.api_token'))
            && filled((string) config('security_monitoring.cloudflare.zone_id'));

        return response()->json([
            'checked_at' => now()->toIso8601String(),
            'kpis' => [
                'window_minutes' => $windowMinutes,
                'events_total' => $eventsTotal,
                'failed_logins' => $failedLogins,
                'blocked_ips_24h' => $blockedIps24h,
                'open_incidents' => $openIncidents,
            ],
            'integrations' => [
                'cloudflare_enabled' => $cloudflareEnabled,
            ],
            'top_ips' => $topIps,
            'incidents' => $recentIncidents,
        ]);
    }

    public function ingestCloudflare(Request $request): JsonResponse
    {
        /** @var CloudflareSecurityService $cf */
        $cf = app(CloudflareSecurityService::class);
        abort_unless($cf->isEnabled(), 422, 'Cloudflare não configurado.');

        $workerMode = (string) config('security_monitoring.queue.worker_mode', 'process');
        if ($workerMode === 'cron') {
            /** @var SecurityAccessEventWriter $writer */
            $writer = app(SecurityAccessEventWriter::class);
            $minutes = (int) config('security_monitoring.risk.window_minutes', 15);
            $limit = (int) config('security_monitoring.cloudflare.ingest_limit', 250);
            $result = $cf->ingestFirewallEvents($writer, $minutes, $limit);

            $this->logAction(
                $request,
                null,
                'ingest_cloudflare',
                null,
                null,
                ($result['ok'] ?? false) ? 'success' : 'failed',
                (string) ($result['message'] ?? '')
            );

            return response()->json([
                'ok' => (bool) ($result['ok'] ?? false),
                'message' => (string) ($result['message'] ?? ''),
                'created' => (int) ($result['created'] ?? 0),
                'mode' => 'cron',
            ], ($result['ok'] ?? false) ? 200 : 422);
        }

        SecurityIngestCloudflareEventsJob::dispatchAsync();
        $this->logAction($request, null, 'ingest_cloudflare', null, null, 'queued', 'Ingestao Cloudflare enfileirada (fila: ' . config('security_monitoring.queue.name', 'maintenance') . ').');

        return response()->json([
            'ok' => true,
            'message' => 'Ingestao Cloudflare enfileirada com sucesso.',
            'mode' => 'process',
        ]);
    }

    public function evaluateRisk(Request $request): JsonResponse
    {
        $workerMode = (string) config('security_monitoring.queue.worker_mode', 'process');
        if ($workerMode === 'cron') {
            $window = (int) config('security_monitoring.risk.window_minutes', 15);
            /** @var SecurityRiskEvaluatorService $svc */
            $svc = app(SecurityRiskEvaluatorService::class);
            $result = $svc->evaluate($window);

            $this->logAction(
                $request,
                null,
                'evaluate_risk',
                null,
                null,
                ($result['ok'] ?? false) ? 'success' : 'failed',
                (string) ($result['message'] ?? '')
            );

            return response()->json([
                'ok' => (bool) ($result['ok'] ?? false),
                'message' => (string) ($result['message'] ?? ''),
                'incidents_upserted' => (int) ($result['incidents_upserted'] ?? 0),
                'mode' => 'cron',
            ], ($result['ok'] ?? false) ? 200 : 422);
        }

        SecurityEvaluateAccessRiskJob::dispatchAsync();
        $this->logAction($request, null, 'evaluate_risk', null, null, 'queued', 'Avaliacao de risco enfileirada (fila: ' . config('security_monitoring.queue.name', 'maintenance') . ').');

        return response()->json([
            'ok' => true,
            'message' => 'Avaliacao de risco enfileirada com sucesso.',
            'mode' => 'process',
        ]);
    }

    public function acknowledgeIncident(Request $request, int $id): JsonResponse
    {
        abort_unless(Schema::hasTable('security_access_incidents'), 404, 'Incidentes de seguranca nao disponiveis.');

        $incident = DB::table('security_access_incidents')->where('id', $id)->first();
        abort_unless($incident, 404, 'Incidente nao encontrado.');

        $comment = trim((string) $request->input('comment', ''));
        DB::table('security_access_incidents')
            ->where('id', $id)
            ->update([
                'status' => 'acknowledged',
                'acknowledged_at' => now(),
                'acknowledged_by_user_id' => $request->user()?->id,
                'ack_comment' => $comment !== '' ? $comment : null,
                'updated_at' => now(),
            ]);

        $this->logAction(
            $request,
            $id,
            'ack_incident',
            'incident',
            (string) $id,
            'success',
            $comment !== '' ? $comment : 'Incidente reconhecido.'
        );

        return response()->json([
            'ok' => true,
            'message' => 'Incidente reconhecido.',
        ]);
    }

    public function blockIp(Request $request): JsonResponse
    {
        $ip = trim((string) $request->input('ip', ''));
        abort_unless($ip !== '', 422, 'IP obrigatório.');
        /** @var CloudflareSecurityService $cf */
        $cf = app(CloudflareSecurityService::class);
        abort_unless($cf->isEnabled(), 422, 'Cloudflare não configurado.');

        $result = $cf->ensureIpRule('block', $ip);
        $this->logAction($request, null, 'block_ip', 'ip', $ip, $result['ok'] ? 'success' : 'failed', (string) ($result['message'] ?? ''));

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function challengeIp(Request $request): JsonResponse
    {
        $ip = trim((string) $request->input('ip', ''));
        abort_unless($ip !== '', 422, 'IP obrigatório.');
        /** @var CloudflareSecurityService $cf */
        $cf = app(CloudflareSecurityService::class);
        abort_unless($cf->isEnabled(), 422, 'Cloudflare não configurado.');

        $result = $cf->ensureIpRule('challenge', $ip);
        $this->logAction($request, null, 'challenge_ip', 'ip', $ip, $result['ok'] ? 'success' : 'failed', (string) ($result['message'] ?? ''));

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function unblockIp(Request $request): JsonResponse
    {
        $ip = trim((string) $request->input('ip', ''));
        abort_unless($ip !== '', 422, 'IP obrigatório.');
        /** @var CloudflareSecurityService $cf */
        $cf = app(CloudflareSecurityService::class);
        abort_unless($cf->isEnabled(), 422, 'Cloudflare não configurado.');

        $result = $cf->removeIpRule($ip);
        $this->logAction($request, null, 'unblock_ip', 'ip', $ip, $result['ok'] ? 'success' : 'failed', (string) ($result['message'] ?? ''));

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function exportIncidentsCsv(): StreamedResponse
    {
        abort_unless(Schema::hasTable('security_access_incidents'), 404, 'Incidentes de seguranca nao disponiveis.');

        $rows = DB::table('security_access_incidents')
            ->orderByDesc('last_seen_at')
            ->limit(5000)
            ->get();

        $filename = 'security-incidents-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if (!$out) {
                return;
            }
            fputcsv($out, ['id', 'level', 'title', 'status', 'event_count', 'first_seen_at', 'last_seen_at', 'acknowledged_at']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    (int) ($row->id ?? 0),
                    (string) ($row->level ?? ''),
                    (string) ($row->title ?? ''),
                    (string) ($row->status ?? ''),
                    (int) ($row->event_count ?? 0),
                    optional($row->first_seen_at)?->toDateTimeString(),
                    optional($row->last_seen_at)?->toDateTimeString(),
                    optional($row->acknowledged_at)?->toDateTimeString(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function logAction(
        Request $request,
        ?int $incidentId,
        string $action,
        ?string $targetType,
        ?string $targetValue,
        string $result,
        string $message
    ): void {
        if (!Schema::hasTable('security_access_actions')) {
            return;
        }

        DB::table('security_access_actions')->insert([
            'incident_id' => $incidentId,
            'action' => $action,
            'target_type' => $targetType,
            'target_value' => $targetValue,
            'result' => $result,
            'message' => $message,
            'actor_user_id' => $request->user()?->id,
            'context_json' => null,
            'created_at' => now(),
        ]);
    }
}
