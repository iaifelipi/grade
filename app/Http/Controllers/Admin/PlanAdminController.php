<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanChangeLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\LeadSource;
use Illuminate\Http\Request;

class PlanAdminController extends Controller
{
    public function index()
    {
        $auth = auth()->user();
        if (!$auth->isSuperAdmin()) {
            abort(403, 'Apenas super admin.');
        }

        $tenantUuid = app()->bound('tenant_uuid') ? (string) app('tenant_uuid') : (string) ($auth->tenant_uuid ?? '');
        $currentSourceId = (int) session('explore_source_id', 0);
        $isGlobalSuper = $auth->isSuperAdmin() && !session()->has('impersonate_user_id');

        $plans = config('plans.available', []);
        $contas = Tenant::query()
            ->whereNotIn('uuid', User::query()->where('is_super_admin', true)->pluck('tenant_uuid')->filter()->all())
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'plan', 'created_at']);

        $sourcesQuery = $isGlobalSuper
            ? LeadSource::withoutGlobalScopes()
            : LeadSource::query()->where('tenant_uuid', $tenantUuid);
        $topbarSources = $sourcesQuery
            ->orderByDesc('id')
            ->get(['id', 'original_name']);

        return view('admin.plans.index', compact('plans', 'contas', 'topbarSources', 'currentSourceId'));
    }

    public function update(Request $request, int $id)
    {
        $auth = auth()->user();
        if (!$auth->isSuperAdmin()) {
            abort(403, 'Apenas super admin.');
        }

        $data = $request->validate([
            'plan' => ['required', 'in:free,starter,pro'],
            'current_plan' => ['nullable', 'in:free,starter,pro'],
            'confirm_downgrade' => ['nullable', 'in:1'],
        ]);

        $tenant = Tenant::findOrFail($id);
        $current = $data['current_plan'] ?? $tenant->plan;
        $isDowngrade = in_array($current, ['starter','pro'], true) && $data['plan'] === 'free';
        if ($isDowngrade && empty($data['confirm_downgrade'])) {
            return back()->with('status', 'Confirme o downgrade para continuar.');
        }

        $tenant->plan = $data['plan'];
        $tenant->save();

        PlanChangeLog::create([
            'tenant_id' => $tenant->id,
            'from_plan' => $current,
            'to_plan' => $data['plan'],
            'changed_by' => $auth->id,
            'note' => $isDowngrade ? 'Downgrade confirmado' : null,
        ]);

        return back()->with('status', 'Plano atualizado.');
    }
}
