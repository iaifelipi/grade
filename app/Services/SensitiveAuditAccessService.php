<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SensitiveAuditAccessService
{
    private static ?bool $tableExists = null;

    private function hasTable(): bool
    {
        if (self::$tableExists === null) {
            self::$tableExists = Schema::hasTable('audit_sensitive_access_logs');
        }

        return self::$tableExists;
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

    public function logView(Request $request, int $sensitiveCount, int $statusCode): void
    {
        if ($sensitiveCount <= 0) {
            return;
        }

        $userId = auth()->id();
        if (!$userId) {
            return;
        }

        $payload = [
            'user_id' => (int) $userId,
            'permission_name' => 'audit.view_sensitive',
            'route_name' => (string) optional($request->route())->getName(),
            'request_path' => substr((string) $request->path(), 0, 255),
            'http_method' => strtoupper(substr((string) $request->method(), 0, 16)),
            'response_status' => $statusCode,
            'sensitive_fields_count' => max(0, (int) $sensitiveCount),
            'ip_hash' => $this->hashValue($request->ip()),
            'ua_hash' => $this->hashValue((string) $request->userAgent()),
            'created_at' => now(),
        ];

        if ($this->hasTable()) {
            DB::table('audit_sensitive_access_logs')->insert($payload);
            return;
        }

        Log::info('sensitive audit access', $payload);
    }
}

