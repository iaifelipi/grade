<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SecurityRiskEvaluatorService
{
    /**
     * @return array{ok:bool, message:string, incidents_upserted:int}
     */
    public function evaluate(int $windowMinutes = 15): array
    {
        if (!Schema::hasTable('security_access_events') || !Schema::hasTable('security_access_incidents')) {
            return ['ok' => false, 'message' => 'Tabelas de segurança não existem.', 'incidents_upserted' => 0];
        }

        $windowMinutes = max(1, $windowMinutes);
        $since = now()->subMinutes($windowMinutes);

        $loginFailThreshold = (int) config('security_monitoring.risk.thresholds.login_failed_per_ip', 20);
        $forbiddenThreshold = (int) config('security_monitoring.risk.thresholds.forbidden_per_ip', 50);
        $firewallThreshold = (int) config('security_monitoring.risk.thresholds.firewall_events_per_ip', 30);

        $upserted = 0;

        // 1) brute-force: login_failed spikes per IP
        $loginFails = DB::table('security_access_events')
            ->where('occurred_at', '>=', $since)
            ->where('event_type', 'login_failed')
            ->whereNotNull('ip_address')
            ->select('ip_address', DB::raw('COUNT(*) as total'), DB::raw('MIN(occurred_at) as first_seen'), DB::raw('MAX(occurred_at) as last_seen'))
            ->groupBy('ip_address')
            ->having('total', '>=', $loginFailThreshold)
            ->orderByDesc('total')
            ->limit(200)
            ->get();

        foreach ($loginFails as $row) {
            $ip = (string) $row->ip_address;
            $key = 'bruteforce:ip:' . $ip;
            $title = "Brute-force (IP {$ip})";
            $level = ((int) $row->total) >= ($loginFailThreshold * 2) ? 'critical' : 'warning';
            $this->upsertIncident($key, $title, $level, (int) $row->total, $row->first_seen, $row->last_seen, [
                'type' => 'bruteforce',
                'ip' => $ip,
                'window_minutes' => $windowMinutes,
            ]);
            $upserted++;
        }

        // 2) forbidden spikes: 401/403 per IP (usually scanning)
        $forbidden = DB::table('security_access_events')
            ->where('occurred_at', '>=', $since)
            ->whereIn('http_status', [401, 403])
            ->whereNotNull('ip_address')
            ->select('ip_address', DB::raw('COUNT(*) as total'), DB::raw('MIN(occurred_at) as first_seen'), DB::raw('MAX(occurred_at) as last_seen'))
            ->groupBy('ip_address')
            ->having('total', '>=', $forbiddenThreshold)
            ->orderByDesc('total')
            ->limit(200)
            ->get();

        foreach ($forbidden as $row) {
            $ip = (string) $row->ip_address;
            $key = 'forbidden_spike:ip:' . $ip;
            $title = "Scan/403 spike (IP {$ip})";
            $level = ((int) $row->total) >= ($forbiddenThreshold * 2) ? 'critical' : 'warning';
            $this->upsertIncident($key, $title, $level, (int) $row->total, $row->first_seen, $row->last_seen, [
                'type' => 'forbidden_spike',
                'ip' => $ip,
                'window_minutes' => $windowMinutes,
            ]);
            $upserted++;
        }

        // 3) Cloudflare firewall event spikes
        $firewall = DB::table('security_access_events')
            ->where('occurred_at', '>=', $since)
            ->where('source', 'cloudflare')
            ->where('event_type', 'firewall_event')
            ->whereNotNull('ip_address')
            ->select('ip_address', DB::raw('COUNT(*) as total'), DB::raw('MIN(occurred_at) as first_seen'), DB::raw('MAX(occurred_at) as last_seen'))
            ->groupBy('ip_address')
            ->having('total', '>=', $firewallThreshold)
            ->orderByDesc('total')
            ->limit(200)
            ->get();

        foreach ($firewall as $row) {
            $ip = (string) $row->ip_address;
            $key = 'firewall_spike:ip:' . $ip;
            $title = "Cloudflare firewall spike (IP {$ip})";
            $level = ((int) $row->total) >= ($firewallThreshold * 2) ? 'critical' : 'warning';
            $this->upsertIncident($key, $title, $level, (int) $row->total, $row->first_seen, $row->last_seen, [
                'type' => 'firewall_spike',
                'ip' => $ip,
                'window_minutes' => $windowMinutes,
            ]);
            $upserted++;
        }

        return ['ok' => true, 'message' => "Incidentes upsertados: {$upserted}", 'incidents_upserted' => $upserted];
    }

    /**
     * @param array<string,mixed> $context
     */
    private function upsertIncident(
        string $key,
        string $title,
        string $level,
        int $eventCount,
        $firstSeenAt,
        $lastSeenAt,
        array $context
    ): void {
        $existing = DB::table('security_access_incidents')->where('key', $key)->first();

        if ($existing) {
            // If it was acknowledged/resolved, keep status but update counters and last_seen.
            DB::table('security_access_incidents')
                ->where('key', $key)
                ->update([
                    'level' => $level,
                    'title' => $title,
                    'event_count' => $eventCount,
                    'first_seen_at' => $existing->first_seen_at ?: $firstSeenAt,
                    'last_seen_at' => $lastSeenAt,
                    'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            return;
        }

        DB::table('security_access_incidents')->insert([
            'level' => $level,
            'status' => 'open',
            'title' => $title,
            'key' => $key,
            'event_count' => $eventCount,
            'first_seen_at' => $firstSeenAt,
            'last_seen_at' => $lastSeenAt,
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

