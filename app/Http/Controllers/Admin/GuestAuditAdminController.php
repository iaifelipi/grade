<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadSource;
use App\Services\SensitiveAuditAccessService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GuestAuditAdminController extends Controller
{
    public function index(Request $request, SensitiveAuditAccessService $sensitiveAudit): \Illuminate\View\View
    {
        $authUser = auth()->user();
        $isGlobalSuper = $authUser && $authUser->isSuperAdmin() && !session()->has('impersonate_user_id');
        $tenantUuid = app()->bound('tenant_uuid') ? (string) app('tenant_uuid') : (string) ($authUser->tenant_uuid ?? '');
        $currentSourceId = (int) session('explore_source_id', 0);

        $sourcesQuery = $isGlobalSuper
            ? LeadSource::withoutGlobalScopes()
            : LeadSource::query()->where('tenant_uuid', $tenantUuid);
        $topbarSources = $sourcesQuery
            ->orderByDesc('id')
            ->get(['id', 'original_name']);

        $filters = [
            'guest_uuid' => trim((string) $request->query('guest_uuid', '')),
            'action' => trim((string) $request->query('action', '')),
            'actor_type' => trim((string) $request->query('actor_type', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];

        $events = new LengthAwarePaginator([], 0, 30);
        $sessions = new LengthAwarePaginator([], 0, 20);
        $accessLogs = new LengthAwarePaginator([], 0, 20);

        if (Schema::hasTable('guest_file_events')) {
            $eventsQuery = DB::table('guest_file_events')
                ->orderByDesc('id');

            if ($filters['guest_uuid'] !== '') {
                $eventsQuery->where('guest_uuid', $filters['guest_uuid']);
            }
            if ($filters['action'] !== '') {
                $eventsQuery->where('action', $filters['action']);
            }
            if ($filters['actor_type'] !== '' && Schema::hasColumn('guest_file_events', 'actor_type')) {
                $eventsQuery->where('actor_type', $filters['actor_type']);
            }
            if ($filters['date_from'] !== '') {
                $eventsQuery->whereDate('created_at', '>=', $filters['date_from']);
            }
            if ($filters['date_to'] !== '') {
                $eventsQuery->whereDate('created_at', '<=', $filters['date_to']);
            }

            $events = $eventsQuery->paginate(30, ['*'], 'events_page')->withQueryString();
        }

        if (Schema::hasTable('guest_sessions')) {
            $sessionsQuery = DB::table('guest_sessions')
                ->orderByDesc('updated_at')
                ->orderByDesc('id');

            if ($filters['guest_uuid'] !== '') {
                $sessionsQuery->where('guest_uuid', $filters['guest_uuid']);
            }
            if ($filters['actor_type'] !== '' && Schema::hasColumn('guest_sessions', 'actor_type')) {
                $sessionsQuery->where('actor_type', $filters['actor_type']);
            }
            if ($filters['date_from'] !== '') {
                $sessionsQuery->whereDate('updated_at', '>=', $filters['date_from']);
            }
            if ($filters['date_to'] !== '') {
                $sessionsQuery->whereDate('updated_at', '<=', $filters['date_to']);
            }

            $sessions = $sessionsQuery->paginate(20, ['*'], 'sessions_page')->withQueryString();
        }

        if (Schema::hasTable('audit_sensitive_access_logs')) {
            $accessLogs = DB::table('audit_sensitive_access_logs')
                ->orderByDesc('id')
                ->paginate(20, ['*'], 'access_page')
                ->withQueryString();
        }

        $sensitiveRows = $events instanceof \Illuminate\Contracts\Pagination\Paginator
            ? (int) $events->count()
            : 0;
        $sensitiveAudit->logView($request, max(1, $sensitiveRows * 3), 200);

        return view('admin.audit.access', [
            'filters' => $filters,
            'events' => $events,
            'sessions' => $sessions,
            'accessLogs' => $accessLogs,
            'topbarSources' => $topbarSources,
            'currentSourceId' => $currentSourceId,
        ]);
    }
}
