<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BugReport;
use App\Models\LeadSource;
use Illuminate\Http\Request;

class BugReportAdminController extends Controller
{
    public function index(Request $request)
    {
        $reports = BugReport::query()
            ->orderByDesc('id')
            ->paginate(30);

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

        return view('admin.reports.index', [
            'reports' => $reports,
            'topbarSources' => $topbarSources,
            'currentSourceId' => $currentSourceId,
        ]);
    }
}
