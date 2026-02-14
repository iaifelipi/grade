<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanChangeLog;
use App\Models\PricePlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\LeadSource;
use App\Services\TenantUsers\TenantUserGroupProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $pricePlans = PricePlan::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'billing_interval', 'amount_minor', 'currency_code', 'is_active']);

        $pricePlanOptions = $pricePlans->map(function (PricePlan $pricePlan) {
            $label = trim((string) ($pricePlan->name ?: ''));
            if ($label === '') {
                $label = ucfirst((string) $pricePlan->code);
            }

            return [
                'id' => (int) $pricePlan->id,
                'label' => $label,
            ];
        })->values()->all();
        $contas = Tenant::query()
            ->whereNotIn('uuid', User::query()->where('is_super_admin', true)->pluck('tenant_uuid')->filter()->all())
            ->withCount([
                'tenantUsers as tenant_users_count' => fn ($q) => $q
                    ->whereNull('deleted_at')
                    ->where('status', 'active'),
            ])
            ->with([
                'currentSubscription.pricePlan:id,code,name,currency_code,amount_minor,is_active',
            ])
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'plan', 'slug', 'created_at']);

        $totalContas = (int) $contas->count();
        $totalAtivas = (int) $contas->filter(fn (Tenant $t) => $t->currentSubscription !== null)->count();
        $totalSemAssinatura = max(0, $totalContas - $totalAtivas);
        $totalUsuarios = (int) $contas->sum('tenant_users_count');

        $sourcesQuery = $isGlobalSuper
            ? LeadSource::withoutGlobalScopes()
            : LeadSource::query()->where('tenant_uuid', $tenantUuid);
        $topbarSources = $sourcesQuery
            ->orderByDesc('id')
            ->get(['id', 'original_name']);

        return view('admin.subscriptions.index', compact(
            'contas',
            'pricePlanOptions',
            'topbarSources',
            'currentSourceId',
            'totalContas',
            'totalAtivas',
            'totalSemAssinatura',
            'totalUsuarios'
        ));
    }

    public function update(Request $request, int $id, TenantUserGroupProvisioningService $groupProvisioning)
    {
        $auth = auth()->user();
        if (!$auth->isSuperAdmin()) {
            abort(403, 'Apenas super admin.');
        }

        $data = $request->validate([
            'price_plan_id' => ['required', 'integer', Rule::exists('monetization_price_plans', 'id')],
            'current_price_plan_id' => ['nullable', 'integer'],
            'confirm_downgrade' => ['nullable', 'in:1'],
        ]);

        $tenant = Tenant::findOrFail($id);
        $nextPricePlan = PricePlan::query()->findOrFail((int) $data['price_plan_id']);

        $currentSubscription = TenantSubscription::query()
            ->where('tenant_id', (int) $tenant->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();
        $currentPricePlan = $currentSubscription
            ? PricePlan::query()->find((int) $currentSubscription->price_plan_id)
            : null;

        $currentCode = strtolower((string) ($currentPricePlan->code ?? $tenant->plan ?? ''));
        $nextCode = strtolower((string) $nextPricePlan->code);
        $isDowngrade = (int) ($currentPricePlan->amount_minor ?? 0) > 0
            && (int) $nextPricePlan->amount_minor === 0;
        if ($isDowngrade && empty($data['confirm_downgrade'])) {
            return back()->with('status', 'Confirme o downgrade para continuar.');
        }

        DB::transaction(function () use (
            $tenant,
            $nextPricePlan,
            $currentSubscription,
            $groupProvisioning
        ): void {
            if ($currentSubscription) {
                $currentSubscription->update([
                    'price_plan_id' => (int) $nextPricePlan->id,
                    'tenant_uuid' => (string) $tenant->uuid,
                    'status' => 'active',
                    'ended_at' => null,
                ]);
            } else {
                TenantSubscription::query()->create([
                    'tenant_id' => (int) $tenant->id,
                    'tenant_uuid' => (string) $tenant->uuid,
                    'price_plan_id' => (int) $nextPricePlan->id,
                    'status' => 'active',
                    'started_at' => now(),
                    'ended_at' => null,
                    'metadata_json' => ['source' => 'admin_customers_subscriptions'],
                ]);
            }

            $tenant->plan = strtolower((string) $nextPricePlan->code);
            $tenant->save();

            $tenantUsersUpdates = [];
            if (Schema::hasColumn('tenant_users', 'price_plan_id')) {
                $tenantUsersUpdates['price_plan_id'] = (int) $nextPricePlan->id;
            }
            if ($tenantUsersUpdates !== []) {
                DB::table('tenant_users')
                    ->where('tenant_id', (int) $tenant->id)
                    ->whereNull('deleted_at')
                    ->update($tenantUsersUpdates);
            }

            $groupProvisioning->provisionForPlan($tenant, (string) $tenant->plan);
        });

        PlanChangeLog::create([
            'tenant_id' => $tenant->id,
            'from_plan' => $currentCode !== '' ? $currentCode : null,
            'to_plan' => $nextCode !== '' ? $nextCode : null,
            'changed_by' => $auth->id,
            'note' => $isDowngrade ? 'Downgrade confirmado' : null,
        ]);

        return back()->with('status', 'Assinatura atualizada.');
    }
}
