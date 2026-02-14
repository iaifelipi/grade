<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminAuditAdminController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('admin_audit_events')) {
            abort(404);
        }

        $q = trim((string) $request->query('q', ''));
        $eventType = trim((string) $request->query('event_type', ''));
        $tenantUuid = trim((string) $request->query('tenant_uuid', ''));

        $rows = DB::table('admin_audit_events')
            ->leftJoin('users as actor', 'actor.id', '=', 'admin_audit_events.actor_user_id')
            ->leftJoin('users as target', 'target.id', '=', 'admin_audit_events.target_user_id')
            ->select([
                'admin_audit_events.id',
                'admin_audit_events.event_type',
                'admin_audit_events.actor_user_id',
                'admin_audit_events.target_user_id',
                'admin_audit_events.tenant_uuid',
                'admin_audit_events.ip_address',
                'admin_audit_events.user_agent',
                'admin_audit_events.payload_json',
                'admin_audit_events.occurred_at',
                DB::raw('actor.email as actor_email'),
                DB::raw('actor.name as actor_name'),
                DB::raw('target.email as target_email'),
                DB::raw('target.name as target_name'),
            ])
            ->when($eventType !== '', fn ($qb) => $qb->where('admin_audit_events.event_type', $eventType))
            ->when($tenantUuid !== '', fn ($qb) => $qb->where('admin_audit_events.tenant_uuid', $tenantUuid))
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('actor.email', 'like', "%{$q}%")
                        ->orWhere('target.email', 'like', "%{$q}%")
                        ->orWhere('admin_audit_events.event_type', 'like', "%{$q}%")
                        ->orWhere('admin_audit_events.ip_address', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('admin_audit_events.id')
            ->paginate(50)
            ->withQueryString();

        $eventTypes = DB::table('admin_audit_events')
            ->select('event_type')
            ->groupBy('event_type')
            ->orderBy('event_type')
            ->pluck('event_type')
            ->all();

        $tenants = DB::table('admin_audit_events')
            ->select('tenant_uuid')
            ->whereNotNull('tenant_uuid')
            ->groupBy('tenant_uuid')
            ->orderBy('tenant_uuid')
            ->pluck('tenant_uuid')
            ->all();

        return view('admin.audit.admin-actions', [
            'rows' => $rows,
            'eventTypes' => $eventTypes,
            'tenants' => $tenants,
            'filters' => [
                'q' => $q,
                'event_type' => $eventType,
                'tenant_uuid' => $tenantUuid,
            ],
        ]);
    }
}

