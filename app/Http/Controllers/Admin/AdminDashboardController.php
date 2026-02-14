<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(Request $request, AdminDashboardService $dashboardService): View
    {
        $user = $request->user();
        abort_unless($user, 401);

        $canAccess = $user->isSuperAdmin()
            || $user->hasPermission('users.manage')
            || $user->hasPermission('roles.manage')
            || $user->hasPermission('system.settings')
            || $user->hasPermission('audit.view_sensitive');

        abort_unless($canAccess, 403);

        $period = (int) $request->query('period', 7);
        if (!in_array($period, [7, 30, 90], true)) {
            $period = 7;
        }

        $isGlobalSuper = $user->isSuperAdmin() && !session()->has('impersonate_user_id');
        $tenantUuid = $isGlobalSuper
            ? trim((string) $request->query('tenant_uuid', ''))
            : trim((string) ($user->tenant_uuid ?? ''));

        return view('admin.dashboard.index', $dashboardService->build(
            $user,
            $period,
            $tenantUuid,
            $isGlobalSuper
        ));
    }
}

