<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminAuditService
{
    /**
     * @param array<string,mixed> $payload
     */
    public function log(string $eventType, ?int $targetUserId = null, array $payload = [], ?Request $request = null): void
    {
        if (!Schema::hasTable('admin_audit_events')) {
            return;
        }

        $actorId = auth()->id();
        $tenantUuid = null;
        if (app()->bound('tenant_uuid')) {
            $tenantUuid = (string) app('tenant_uuid');
        } elseif (auth()->check()) {
            $tenantUuid = (string) (auth()->user()->tenant_uuid ?? '');
        }
        $tenantUuid = $tenantUuid !== '' ? $tenantUuid : null;

        $req = $request ?: request();

        DB::table('admin_audit_events')->insert([
            'event_type' => $eventType,
            'actor_user_id' => $actorId ? (int) $actorId : null,
            'target_user_id' => $targetUserId,
            'tenant_uuid' => $tenantUuid,
            'ip_address' => $req?->ip(),
            'user_agent' => $req?->userAgent(),
            'payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'occurred_at' => now(),
        ]);
    }
}

