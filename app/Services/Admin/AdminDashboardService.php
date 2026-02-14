<?php

namespace App\Services\Admin;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\TenantUserGroup;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardService
{
    /**
     * @return array<string,mixed>
     */
    public function build(User $actor, int $periodDays, string $tenantUuid, bool $isGlobalSuper): array
    {
        $tenantUuid = $this->resolveTenantUuid($tenantUuid, $isGlobalSuper, $actor);
        $cacheKey = implode(':', [
            'admin_dashboard',
            $actor->id,
            $isGlobalSuper ? 'global' : 'tenant',
            $tenantUuid !== '' ? $tenantUuid : 'all',
            $periodDays,
        ]);

        $payload = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($periodDays, $tenantUuid): array {
            $since = now()->subDays($periodDays);

            return [
                'kpis' => $this->kpis($since, $tenantUuid),
                'series' => $this->series($since, $periodDays, $tenantUuid),
                'recentAdminEvents' => $this->recentAdminEvents($tenantUuid),
                'recentSecurityIncidents' => $this->recentSecurityIncidents(),
                'recentLogins' => $this->recentLogins($since, $tenantUuid),
            ];
        });

        $tenantOptions = $isGlobalSuper
            ? Tenant::query()->orderBy('name')->get(['uuid', 'name', 'slug', 'plan'])
            : Tenant::query()->where('uuid', $tenantUuid)->get(['uuid', 'name', 'slug', 'plan']);

        return array_merge($payload, [
            'isGlobalSuper' => $isGlobalSuper,
            'tenantOptions' => $tenantOptions,
            'selectedTenantUuid' => $tenantUuid,
            'selectedTenant' => $tenantOptions->firstWhere('uuid', $tenantUuid),
            'periodDays' => $periodDays,
            'periodOptions' => [7, 30, 90],
        ]);
    }

    private function resolveTenantUuid(string $tenantUuid, bool $isGlobalSuper, User $actor): string
    {
        if (!$isGlobalSuper) {
            return trim((string) ($actor->tenant_uuid ?? ''));
        }

        $tenantUuid = trim($tenantUuid);
        if ($tenantUuid === '') {
            return '';
        }

        return Tenant::query()->where('uuid', $tenantUuid)->exists() ? $tenantUuid : '';
    }

    /**
     * @return array<string,int>
     */
    private function kpis(\Illuminate\Support\Carbon $since, string $tenantUuid): array
    {
        $adminsActive = User::query()
            ->whereNull('disabled_at')
            ->when($tenantUuid !== '', fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->where(function ($q) use ($tenantUuid) {
                $q->where('is_super_admin', true)
                    ->orWhereHas('roles', function ($roleQuery) use ($tenantUuid) {
                        $roleQuery->where('name', 'admin');
                        if ($tenantUuid !== '') {
                            $roleQuery->where('users_groups.tenant_uuid', $tenantUuid);
                        }
                    });
            })
            ->count();

        $customersActive = TenantUser::query()
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->whereNull('deactivate_at')
            ->when($tenantUuid !== '', fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->count();

        $groups = TenantUserGroup::query()
            ->when($tenantUuid !== '', fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->count();

        $pendingInvites = $this->pendingInvitesCount($tenantUuid);
        $loginCount = $this->loginCount($since, $tenantUuid);
        $openIncidents = $this->openSecurityIncidentsCount();

        return [
            'admins_active' => $adminsActive,
            'customers_active' => $customersActive,
            'groups_total' => $groups,
            'logins_period' => $loginCount,
            'invites_pending' => $pendingInvites,
            'security_open' => $openIncidents,
        ];
    }

    private function pendingInvitesCount(string $tenantUuid): int
    {
        $count = 0;

        if (Schema::hasTable('tenant_user_invitations')) {
            $count += (int) DB::table('tenant_user_invitations')
                ->when($tenantUuid !== '', fn ($q) => $q->where('tenant_uuid', $tenantUuid))
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count();
        }

        return $count;
    }

    private function loginCount(\Illuminate\Support\Carbon $since, string $tenantUuid): int
    {
        if (!Schema::hasTable('security_access_events')) {
            return 0;
        }

        $query = DB::table('security_access_events as e')
            ->where('e.occurred_at', '>=', $since)
            ->whereIn('e.event_type', ['login_success', 'tenant_login_success']);

        if ($tenantUuid !== '') {
            $query->where(function ($q) use ($tenantUuid) {
                $q->whereExists(function ($sub) use ($tenantUuid) {
                    $sub->select(DB::raw(1))
                        ->from('users as u')
                        ->whereColumn('u.id', 'e.user_id')
                        ->where('u.tenant_uuid', $tenantUuid);
                })->orWhereExists(function ($sub) use ($tenantUuid) {
                    $sub->select(DB::raw(1))
                        ->from('tenant_users as tu')
                        ->whereColumn('tu.email', 'e.user_email')
                        ->where('tu.tenant_uuid', $tenantUuid)
                        ->whereNull('tu.deleted_at');
                });
            });
        }

        return (int) $query->count();
    }

    private function openSecurityIncidentsCount(): int
    {
        if (!Schema::hasTable('security_access_incidents')) {
            return 0;
        }

        return (int) DB::table('security_access_incidents')
            ->whereIn('status', ['open', 'acknowledged'])
            ->count();
    }

    /**
     * @return array<string,array<int,int>>
     */
    private function series(\Illuminate\Support\Carbon $since, int $periodDays, string $tenantUuid): array
    {
        return [
            'logins' => $this->loginSeries($since, $periodDays, $tenantUuid),
            'admin_events' => $this->adminEventsSeries($since, $periodDays, $tenantUuid),
            'security_incidents' => $this->securityIncidentsSeries($since, $periodDays),
        ];
    }

    /**
     * @return array<int,int>
     */
    private function loginSeries(\Illuminate\Support\Carbon $since, int $periodDays, string $tenantUuid): array
    {
        if (!Schema::hasTable('security_access_events')) {
            return array_fill(0, $periodDays, 0);
        }

        $query = DB::table('security_access_events as e')
            ->selectRaw('DATE(e.occurred_at) as d, COUNT(*) as c')
            ->where('e.occurred_at', '>=', $since)
            ->whereIn('e.event_type', ['login_success', 'tenant_login_success']);

        if ($tenantUuid !== '') {
            $query->where(function ($q) use ($tenantUuid) {
                $q->whereExists(function ($sub) use ($tenantUuid) {
                    $sub->select(DB::raw(1))
                        ->from('users as u')
                        ->whereColumn('u.id', 'e.user_id')
                        ->where('u.tenant_uuid', $tenantUuid);
                })->orWhereExists(function ($sub) use ($tenantUuid) {
                    $sub->select(DB::raw(1))
                        ->from('tenant_users as tu')
                        ->whereColumn('tu.email', 'e.user_email')
                        ->where('tu.tenant_uuid', $tenantUuid)
                        ->whereNull('tu.deleted_at');
                });
            });
        }

        $rows = $query->groupBy('d')->pluck('c', 'd');

        return $this->normalizeDailySeries($rows, $periodDays);
    }

    /**
     * @return array<int,int>
     */
    private function adminEventsSeries(\Illuminate\Support\Carbon $since, int $periodDays, string $tenantUuid): array
    {
        if (!Schema::hasTable('admin_audit_events')) {
            return array_fill(0, $periodDays, 0);
        }

        $rows = DB::table('admin_audit_events')
            ->selectRaw('DATE(occurred_at) as d, COUNT(*) as c')
            ->where('occurred_at', '>=', $since)
            ->when($tenantUuid !== '', fn ($q) => $q->where('tenant_uuid', $tenantUuid))
            ->groupBy('d')
            ->pluck('c', 'd');

        return $this->normalizeDailySeries($rows, $periodDays);
    }

    /**
     * @return array<int,int>
     */
    private function securityIncidentsSeries(\Illuminate\Support\Carbon $since, int $periodDays): array
    {
        if (!Schema::hasTable('security_access_incidents')) {
            return array_fill(0, $periodDays, 0);
        }

        $rows = DB::table('security_access_incidents')
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->where('created_at', '>=', $since)
            ->groupBy('d')
            ->pluck('c', 'd');

        return $this->normalizeDailySeries($rows, $periodDays);
    }

    /**
     * @param \Illuminate\Support\Collection<string,int>|array<string,int> $rows
     * @return array<int,int>
     */
    private function normalizeDailySeries($rows, int $periodDays): array
    {
        $map = collect($rows)->mapWithKeys(fn ($count, $date) => [(string) $date => (int) $count]);
        $out = [];
        for ($i = $periodDays - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $out[] = (int) ($map->get($date, 0));
        }

        return $out;
    }

    /**
     * @return Collection<int,object>
     */
    private function recentAdminEvents(string $tenantUuid): Collection
    {
        if (!Schema::hasTable('admin_audit_events')) {
            return collect();
        }

        return DB::table('admin_audit_events as e')
            ->leftJoin('users as actor', 'actor.id', '=', 'e.actor_user_id')
            ->leftJoin('users as target', 'target.id', '=', 'e.target_user_id')
            ->select([
                'e.id',
                'e.event_type',
                'e.tenant_uuid',
                'e.occurred_at',
                'actor.name as actor_name',
                'target.name as target_name',
            ])
            ->when($tenantUuid !== '', fn ($q) => $q->where('e.tenant_uuid', $tenantUuid))
            ->orderByDesc('e.occurred_at')
            ->limit(8)
            ->get();
    }

    /**
     * @return Collection<int,object>
     */
    private function recentSecurityIncidents(): Collection
    {
        if (!Schema::hasTable('security_access_incidents')) {
            return collect();
        }

        return DB::table('security_access_incidents')
            ->select(['id', 'level', 'status', 'title', 'event_count', 'updated_at'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();
    }

    /**
     * @return Collection<int,object>
     */
    private function recentLogins(\Illuminate\Support\Carbon $since, string $tenantUuid): Collection
    {
        if (!Schema::hasTable('security_access_events')) {
            return collect();
        }

        $query = DB::table('security_access_events as e')
            ->select(['e.id', 'e.event_type', 'e.user_email', 'e.ip_address', 'e.occurred_at'])
            ->where('e.occurred_at', '>=', $since)
            ->whereIn('e.event_type', ['login_success', 'tenant_login_success'])
            ->orderByDesc('e.occurred_at')
            ->limit(8);

        if ($tenantUuid !== '') {
            $query->where(function ($q) use ($tenantUuid) {
                $q->whereExists(function ($sub) use ($tenantUuid) {
                    $sub->select(DB::raw(1))
                        ->from('users as u')
                        ->whereColumn('u.id', 'e.user_id')
                        ->where('u.tenant_uuid', $tenantUuid);
                })->orWhereExists(function ($sub) use ($tenantUuid) {
                    $sub->select(DB::raw(1))
                        ->from('tenant_users as tu')
                        ->whereColumn('tu.email', 'e.user_email')
                        ->where('tu.tenant_uuid', $tenantUuid)
                        ->whereNull('tu.deleted_at');
                });
            });
        }

        return $query->get();
    }
}
