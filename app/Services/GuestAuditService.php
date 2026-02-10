<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GuestAuditService
{
    /** @var array<string,bool> */
    private array $tableCache = [];

    private function hasTable(string $table): bool
    {
        if (!array_key_exists($table, $this->tableCache)) {
            $this->tableCache[$table] = Schema::hasTable($table);
        }

        return $this->tableCache[$table];
    }

    private function hashValue(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $salt = (string) config('app.key', '');
        return hash('sha256', $salt . '|' . $raw);
    }

    private function buildActorUuidForUser(int $userId): string
    {
        return sprintf('00000000-0000-0000-0000-%012x', max(1, $userId));
    }

    private function resolveActorContext(Request $request): ?array
    {
        if (!$request->hasSession()) {
            return null;
        }

        if (auth()->check()) {
            $userId = (int) auth()->id();
            if ($userId > 0) {
                return [
                    'actor_type' => 'user',
                    'guest_uuid' => $this->buildActorUuidForUser($userId),
                    'user_id' => $userId,
                ];
            }
        }

        $guestUuid = (string) $request->session()->get('guest_tenant_uuid', '');
        if (!Str::isUuid($guestUuid) && str_starts_with($guestUuid, 'guest_')) {
            $candidate = (string) substr($guestUuid, 6);
            if (Str::isUuid($candidate)) {
                $guestUuid = $candidate;
                $request->session()->put('guest_tenant_uuid', $guestUuid);
            }
        }

        if (!Str::isUuid($guestUuid)) {
            return null;
        }

        return [
            'actor_type' => 'guest',
            'guest_uuid' => $guestUuid,
            'user_id' => null,
        ];
    }

    private function encryptValue(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Crypt::encryptString($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseClientDevice(Request $request): array
    {
        $ua = trim((string) $request->userAgent());
        $uaLower = strtolower($ua);

        $deviceType = 'desktop';
        if (str_contains($uaLower, 'tablet') || str_contains($uaLower, 'ipad')) {
            $deviceType = 'tablet';
        } elseif (
            str_contains($uaLower, 'mobile')
            || str_contains($uaLower, 'android')
            || str_contains($uaLower, 'iphone')
        ) {
            $deviceType = 'mobile';
        }

        $osFamily = 'Unknown';
        $osVersion = null;
        if (preg_match('/Windows NT ([0-9.]+)/i', $ua, $m)) {
            $osFamily = 'Windows';
            $osVersion = $m[1];
        } elseif (preg_match('/Android ([0-9.]+)/i', $ua, $m)) {
            $osFamily = 'Android';
            $osVersion = $m[1];
        } elseif (preg_match('/iPhone OS ([0-9_]+)/i', $ua, $m)) {
            $osFamily = 'iOS';
            $osVersion = str_replace('_', '.', $m[1]);
        } elseif (preg_match('/iPad; CPU OS ([0-9_]+)/i', $ua, $m)) {
            $osFamily = 'iPadOS';
            $osVersion = str_replace('_', '.', $m[1]);
        } elseif (preg_match('/Mac OS X ([0-9_]+)/i', $ua, $m)) {
            $osFamily = 'macOS';
            $osVersion = str_replace('_', '.', $m[1]);
        } elseif (str_contains($ua, 'Linux')) {
            $osFamily = 'Linux';
        }

        $browserFamily = 'Unknown';
        $browserVersion = null;
        if (preg_match('/Edg\/([0-9.]+)/i', $ua, $m)) {
            $browserFamily = 'Edge';
            $browserVersion = $m[1];
        } elseif (preg_match('/OPR\/([0-9.]+)/i', $ua, $m)) {
            $browserFamily = 'Opera';
            $browserVersion = $m[1];
        } elseif (preg_match('/Chrome\/([0-9.]+)/i', $ua, $m)) {
            $browserFamily = 'Chrome';
            $browserVersion = $m[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/i', $ua, $m)) {
            $browserFamily = 'Firefox';
            $browserVersion = $m[1];
        } elseif (preg_match('/Version\/([0-9.]+).*Safari/i', $ua, $m)) {
            $browserFamily = 'Safari';
            $browserVersion = $m[1];
        }

        $hardwareRaw = null;
        if (str_contains($ua, 'x86_64') || str_contains($ua, 'Win64') || str_contains($ua, 'WOW64')) {
            $hardwareRaw = 'x86_64';
        } elseif (str_contains($ua, 'arm64') || str_contains($ua, 'aarch64')) {
            $hardwareRaw = 'arm64';
        } elseif (str_contains($ua, 'arm')) {
            $hardwareRaw = 'arm';
        }

        return [
            'ua_raw' => $ua !== '' ? $ua : null,
            'os_family' => $osFamily,
            'os_version' => $osVersion,
            'browser_family' => $browserFamily,
            'browser_version' => $browserVersion,
            'device_type' => $deviceType,
            'hardware_raw' => $hardwareRaw,
        ];
    }

    private function encodeJson(?array $value): ?string
    {
        if (!$value) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    private function normalizeLeadSourceId(?int $leadSourceId): ?int
    {
        if ($leadSourceId === null || $leadSourceId <= 0) {
            return null;
        }

        if (!$this->hasTable('lead_sources')) {
            return null;
        }

        $exists = DB::table('lead_sources')
            ->where('id', $leadSourceId)
            ->exists();

        return $exists ? $leadSourceId : null;
    }

    public function touchGuestSession(Request $request, array $meta = []): ?string
    {
        if (!$this->hasTable('guest_sessions')) {
            return null;
        }

        $actor = $this->resolveActorContext($request);
        if (!$actor) {
            return null;
        }
        $guestUuid = (string) $actor['guest_uuid'];

        $now = now();
        $tenantUuid = trim((string) (
            $request->session()->get('tenant_uuid')
            ?? (auth()->check() ? (string) (auth()->user()->tenant_uuid ?? '') : $guestUuid)
        ));
        $device = $this->parseClientDevice($request);
        $payload = [
            'tenant_uuid' => $tenantUuid !== '' ? $tenantUuid : null,
            'actor_type' => (string) ($actor['actor_type'] ?? 'guest'),
            'user_id' => $actor['user_id'] ?? null,
            'session_id' => $request->session()->getId(),
            'ip_raw' => substr(trim((string) $request->ip()), 0, 45) ?: null,
            'ip_enc' => $this->encryptValue((string) $request->ip()),
            'ip_hash' => $this->hashValue($request->ip()),
            'ua_hash' => $this->hashValue((string) $request->userAgent()),
            'ua_raw' => $device['ua_raw'],
            'os_family' => $device['os_family'],
            'os_version' => $device['os_version'],
            'browser_family' => $device['browser_family'],
            'browser_version' => $device['browser_version'],
            'device_type' => $device['device_type'],
            'hardware_raw' => $device['hardware_raw'],
            'last_route' => (string) $request->path(),
            'meta_json' => $this->encodeJson($meta ?: null),
            'last_seen_at' => $now,
            'updated_at' => $now,
        ];

        $exists = DB::table('guest_sessions')
            ->where('guest_uuid', $guestUuid)
            ->exists();

        if ($exists) {
            DB::table('guest_sessions')
                ->where('guest_uuid', $guestUuid)
                ->update($payload);
        } else {
            DB::table('guest_sessions')
                ->insert(array_merge($payload, [
                    'guest_uuid' => $guestUuid,
                    'first_seen_at' => $now,
                    'created_at' => $now,
                ]));
        }

        return $guestUuid;
    }

    public function logFileEvent(Request $request, string $action, array $data = []): void
    {
        if (!$this->hasTable('guest_file_events')) {
            return;
        }

        $actor = $this->resolveActorContext($request);
        if (!$actor) {
            return;
        }
        $guestUuid = (string) $actor['guest_uuid'];
        $this->touchGuestSession($request, ['action' => $action]);

        $device = $this->parseClientDevice($request);
        $tenantUuid = trim((string) (
            $request->session()->get('tenant_uuid')
            ?? (auth()->check() ? (string) (auth()->user()->tenant_uuid ?? '') : $guestUuid)
        ));

        $leadSourceId = isset($data['lead_source_id']) ? (int) $data['lead_source_id'] : null;
        $rawLeadSourceId = $leadSourceId;
        $leadSourceId = $this->normalizeLeadSourceId($leadSourceId);
        if ($leadSourceId === null && $rawLeadSourceId !== null) {
            $payload = !empty($data['payload']) && is_array($data['payload']) ? $data['payload'] : [];
            $payload['deleted_lead_source_id'] = (int) $rawLeadSourceId;
            $data['payload'] = $payload;
        }

        DB::table('guest_file_events')->insert([
            'guest_uuid' => $guestUuid,
            'actor_type' => (string) ($actor['actor_type'] ?? 'guest'),
            'user_id' => $actor['user_id'] ?? null,
            'tenant_uuid' => $tenantUuid ?: null,
            'session_id' => $request->session()->getId(),
            'ip_raw' => substr(trim((string) $request->ip()), 0, 45) ?: null,
            'ip_enc' => $this->encryptValue((string) $request->ip()),
            'lead_source_id' => $leadSourceId,
            'action' => substr(trim($action), 0, 50),
            'file_name' => isset($data['file_name']) ? substr((string) $data['file_name'], 0, 255) : null,
            'file_path' => isset($data['file_path']) ? substr((string) $data['file_path'], 0, 1024) : null,
            'file_hash' => isset($data['file_hash']) ? substr((string) $data['file_hash'], 0, 64) : null,
            'file_size_bytes' => isset($data['file_size_bytes']) ? (int) $data['file_size_bytes'] : null,
            'ua_raw' => $device['ua_raw'],
            'os_family' => $device['os_family'],
            'os_version' => $device['os_version'],
            'browser_family' => $device['browser_family'],
            'browser_version' => $device['browser_version'],
            'device_type' => $device['device_type'],
            'hardware_raw' => $device['hardware_raw'],
            'payload_json' => $this->encodeJson(!empty($data['payload']) && is_array($data['payload']) ? $data['payload'] : null),
            'created_at' => now(),
        ]);
    }
}
