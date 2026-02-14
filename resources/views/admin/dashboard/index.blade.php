@extends('layouts.app')

@section('title', 'Admin — Dashboard')
@section('page-title', 'Admin')

@section('content')
@php
    $user = auth()->user();
    $canUsers = $user && $user->hasPermission('users.manage');
    $canRoles = $user && $user->hasPermission('roles.manage');
    $canSystem = $user && $user->hasPermission('system.settings');
    $canAudit = $user && $user->hasPermission('audit.view_sensitive');
    $sparkline = function (array $values, int $width = 120, int $height = 28): array {
        $points = array_values(array_map(fn ($v) => (int) $v, $values));
        $count = count($points);
        if ($count <= 1) {
            $points = [0, 0];
            $count = 2;
        }

        $max = max($points);
        $min = min($points);
        $range = max(1, $max - $min);
        $stepX = $count > 1 ? $width / ($count - 1) : $width;

        $coords = [];
        foreach ($points as $i => $value) {
            $x = (float) ($stepX * $i);
            $y = (float) ($height - ((($value - $min) / $range) * $height));
            $coords[] = [round($x, 2), round($y, 2)];
        }

        $line = collect($coords)->map(fn ($c) => $c[0] . ',' . $c[1])->implode(' ');
        $fill = '0,' . $height . ' ' . $line . ' ' . $width . ',' . $height;

        return [
            'line' => $line,
            'fill' => $fill,
            'max' => $max,
            'last' => (int) end($points),
        ];
    };
    $sparkLogins = $sparkline((array) ($series['logins'] ?? []));
    $sparkAdminEvents = $sparkline((array) ($series['admin_events'] ?? []));
    $sparkSecurity = $sparkline((array) ($series['security_incidents'] ?? []));
@endphp
<div class="admin-dashboard-page">
    <x-admin.page-header
        title="Dashboard Admin"
        subtitle="Visão consolidada de usuários, clientes, acessos e segurança."
    >
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.dashboard') }}" class="d-flex flex-wrap gap-2 align-items-center">
                <select name="period" class="form-select form-select-sm" style="min-width: 120px;">
                    @foreach($periodOptions as $p)
                        <option value="{{ $p }}" @selected((int) $periodDays === (int) $p)>{{ $p }} dias</option>
                    @endforeach
                </select>
                @if($isGlobalSuper)
                    <select name="tenant_uuid" class="form-select form-select-sm" style="min-width: 220px;">
                        <option value="">Todos os clientes</option>
                        @foreach($tenantOptions as $tenant)
                            <option value="{{ $tenant->uuid }}" @selected($selectedTenantUuid === (string) $tenant->uuid)>
                                {{ $tenant->name }} ({{ $tenant->slug }})
                            </option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="btn btn-outline-secondary btn-sm">Aplicar filtro</button>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-2 mb-3">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-muted small">Admins ativos</div>
                    <div class="fw-bold fs-5">{{ (int) ($kpis['admins_active'] ?? 0) }}</div>
                    <svg viewBox="0 0 120 28" width="100%" height="28" aria-label="Sparkline atividade admin" class="mt-1">
                        <polygon points="{{ $sparkAdminEvents['fill'] }}" fill="rgba(130,95,56,.16)"></polygon>
                        <polyline points="{{ $sparkAdminEvents['line'] }}" fill="none" stroke="#7a5a37" stroke-width="2" stroke-linecap="round"></polyline>
                    </svg>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-muted small">Clientes ativos</div>
                    <div class="fw-bold fs-5">{{ (int) ($kpis['customers_active'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-muted small">Grupos de Clientes</div>
                    <div class="fw-bold fs-5">{{ (int) ($kpis['groups_total'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-muted small">Logins (período)</div>
                    <div class="fw-bold fs-5">{{ (int) ($kpis['logins_period'] ?? 0) }}</div>
                    <svg viewBox="0 0 120 28" width="100%" height="28" aria-label="Sparkline logins" class="mt-1">
                        <polygon points="{{ $sparkLogins['fill'] }}" fill="rgba(37,99,235,.16)"></polygon>
                        <polyline points="{{ $sparkLogins['line'] }}" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round"></polyline>
                    </svg>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-muted small">Convites pendentes</div>
                    <div class="fw-bold fs-5">{{ (int) ($kpis['invites_pending'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-muted small">Incidentes abertos</div>
                    <div class="fw-bold fs-5">{{ (int) ($kpis['security_open'] ?? 0) }}</div>
                    <svg viewBox="0 0 120 28" width="100%" height="28" aria-label="Sparkline incidentes segurança" class="mt-1">
                        <polygon points="{{ $sparkSecurity['fill'] }}" fill="rgba(220,38,38,.14)"></polygon>
                        <polyline points="{{ $sparkSecurity['line'] }}" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round"></polyline>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Ações rápidas</div>
        <div class="card-body d-flex flex-wrap gap-2">
            @if($canUsers)
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Usuários</a>
                <a href="{{ url('/admin/customers') }}" class="btn btn-outline-secondary btn-sm">Clientes</a>
                <a href="{{ route('admin.monetization.dashboard') }}" class="btn btn-outline-secondary btn-sm">Cobranças</a>
            @endif
            @if($canRoles)
                <a href="{{ url('/admin/users/user-groups') }}" class="btn btn-outline-secondary btn-sm">Grupos de Usuários</a>
            @endif
            @if($canUsers)
                <a href="{{ url('/admin/customers/user-groups') }}" class="btn btn-outline-secondary btn-sm">Grupos de Clientes</a>
                <a href="{{ route('admin.customers.subscriptions.index') }}" class="btn btn-outline-secondary btn-sm">Assinaturas</a>
            @endif
            @if($canSystem)
                <a href="{{ route('admin.integrations.index') }}" class="btn btn-outline-secondary btn-sm">Integrações</a>
                <a href="{{ route('admin.semantic.index') }}" class="btn btn-outline-secondary btn-sm">Semântica</a>
                <a href="{{ route('admin.monitoring.index') }}" class="btn btn-outline-secondary btn-sm">Monitoramento</a>
                <a href="{{ route('admin.security.index') }}" class="btn btn-outline-secondary btn-sm">Segurança</a>
                <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary btn-sm">Relatos</a>
            @endif
            @if($canAudit)
                <a href="{{ route('admin.audit.access') }}" class="btn btn-outline-secondary btn-sm">Auditoria</a>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Últimas ações administrativas</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Quando</th>
                                <th>Evento</th>
                                <th>Ator</th>
                                <th>Alvo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentAdminEvents as $event)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($event->occurred_at)->format('d/m H:i') }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $event->event_type }}</span></td>
                                    <td>{{ $event->actor_name ?: 'sistema' }}</td>
                                    <td>{{ $event->target_name ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Sem eventos no período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Últimos logins</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Quando</th>
                                <th>Tipo</th>
                                <th>Usuário</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogins as $login)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($login->occurred_at)->format('d/m H:i') }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $login->event_type }}</span></td>
                                    <td>{{ $login->user_email ?: '—' }}</td>
                                    <td>{{ $login->ip_address ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Sem logins no período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold">Incidentes de segurança recentes</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nível</th>
                        <th>Status</th>
                        <th>Título</th>
                        <th>Eventos</th>
                        <th>Atualizado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentSecurityIncidents as $incident)
                        <tr>
                            <td>#{{ $incident->id }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $incident->level }}</span></td>
                            <td><span class="badge bg-light text-dark border">{{ $incident->status }}</span></td>
                            <td>{{ $incident->title }}</td>
                            <td>{{ (int) $incident->event_count }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($incident->updated_at)->format('d/m H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">Sem incidentes recentes.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
