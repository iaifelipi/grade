@extends('layouts.app')

@section('title','Admin — Grupos de Clientes')
@section('page-title','Admin')

@section('content')
<div class="admin-tenant-groups-page">
    <x-admin.page-header
        title="Grupos de Clientes"
        subtitle="Matriz de permissões dos grupos de clientes."
    >
        <x-slot:actions>
            @if(($isGlobalSuper ?? false) && ($tenants ?? collect())->count())
                <form method="GET" action="{{ url('/admin/customers/user-groups') }}" class="d-flex align-items-center gap-2">
                    <select name="tenant_uuid" class="form-select form-select-sm" style="max-width: 280px;">
                        <option value="">Todos os clientes</option>
                        @foreach($tenants as $t)
                            <option value="{{ $t->uuid }}" @selected((string)($selectedTenantUuid ?? '') === (string)$t->uuid)>
                                {{ $t->name }} ({{ $t->slug ?? $t->uuid }})
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill">Filtrar</button>
                </form>
            @endif
            <a href="{{ url('/admin/customers') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Clientes</a>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Usuários do sistema</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($tenant)
        <div class="admin-tenant-users-tenant-pill mb-3">
            <span class="admin-tenant-users-tenant-label">Cliente</span>
            <strong>{{ $tenant->name }}</strong>
            <code>{{ $tenant->uuid }}</code>
        </div>
    @endif

    @if(session('status'))
        <div class="alert alert-success admin-tenant-users-alert">{{ session('status') }}</div>
    @endif

    <div class="card border-0 shadow-sm admin-tenant-groups-intro mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div>
                <div class="fw-semibold">Política da matriz de permissões</div>
                <div class="small text-muted">Selecione as capacidades por grupo. `*` concede acesso total.</div>
            </div>
            <span class="badge bg-light text-dark border rounded-pill">{{ $groups->count() }} grupos</span>
        </div>
    </div>

    <div class="row g-3">
        @foreach($groups as $group)
            @php
                $current = collect($group->permissions_json ?? [])->map(fn($p) => (string) $p)->values();
                $isWildcard = $current->contains('*');
            @endphp
            <div class="col-12">
                <div class="card border-0 shadow-sm admin-tenant-groups-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <strong>{{ $group->name }}</strong>
                            <span class="text-muted small">({{ $group->slug }})</span>
                            @if($isGlobalSuper ?? false)
                                <span class="badge bg-light text-dark border rounded-pill">
                                    {{ $group->tenant?->slug ?? $group->tenant?->name ?? $group->tenant_uuid }}
                                </span>
                            @endif
                            @if($group->is_default)
                                <span class="badge bg-light text-dark border rounded-pill">padrão</span>
                            @endif
                            <span class="badge rounded-pill {{ $group->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $group->is_active ? 'ativo' : 'inativo' }}
                            </span>
                        </div>
                        <div>
                            <span class="badge bg-light text-dark border rounded-pill">{{ $current->count() }} permissões</span>
                        </div>
                    </div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.tenantUserGroups.permissions.update', $group->id) }}">
                            @csrf
                            @method('PUT')
                            @if($isGlobalSuper ?? false)
                                <input type="hidden" name="tenant_uuid" value="{{ $group->tenant_uuid }}">
                            @endif

                            <div class="row g-2">
                                @foreach($permissionCatalog as $key => $meta)
                                    @php $checked = $current->contains($key); @endphp
                                    <div class="col-md-6 col-xl-4">
                                        <label class="form-check admin-tenant-groups-perm-item {{ $checked ? 'is-checked' : '' }} {{ $key === '*' ? 'is-wildcard' : '' }}">
                                            <input class="admin-tenant-groups-perm-check" type="checkbox" name="permissions[]" value="{{ $key }}" @checked($checked)>
                                            <span class="form-check-label admin-tenant-groups-perm-content">
                                                <span class="admin-tenant-groups-perm-kicker">{{ strtoupper($meta['module']) }}</span>
                                                <span class="admin-tenant-groups-perm-title-row">
                                                    <span class="admin-tenant-groups-perm-title">{{ $key }}</span>
                                                    @if($key === '*')
                                                        <span class="badge bg-light text-dark border rounded-pill">todos os módulos</span>
                                                    @elseif($isWildcard)
                                                        <span class="badge bg-light text-dark border rounded-pill">herdado por *</span>
                                                    @endif
                                                </span>
                                                <span class="admin-tenant-groups-perm-desc">{{ $meta['label'] }}</span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button class="btn btn-dark btn-sm rounded-pill px-4">Salvar permissões</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
