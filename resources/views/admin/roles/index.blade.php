@extends('layouts.app')

@section('title','Admin — Grupos de Usuários')
@section('page-title','Admin')

@section('content')
@php
    $totalRoles = $roles->count();
    $totalPermissions = $permissions->count();
    $scopedTenants = $roles->pluck('tenant_uuid')->filter()->unique()->count();
@endphp
<div class="admin-roles-page">
    <x-admin.page-header
        title="Grupos de Usuários"
        subtitle="Governança de acesso com edição por grupo."
    >
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Usuários</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-metric-field-box">
                        <span class="grade-profile-field-kicker">Perfis</span>
                        <div class="admin-metric-field-value">{{ $totalRoles }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-metric-field-box">
                        <span class="grade-profile-field-kicker">Permissões do sistema</span>
                        <div class="admin-metric-field-value">{{ $totalPermissions }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-metric-field-box">
                        <span class="grade-profile-field-kicker">Contas com perfis</span>
                        <div class="admin-metric-field-value">{{ $scopedTenants }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="fw-semibold">Matriz de permissões</div>
            <div class="d-flex align-items-center gap-2">
                <input id="rolesSearchInput" type="search" class="form-control form-control-sm" placeholder="Buscar perfil" style="min-width: 220px;">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 admin-roles-table admin-enterprise-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:160px">Perfil</th>
                            @if($isGlobalSuper ?? false)
                                <th style="width:180px">Conta</th>
                            @endif
                            <th style="min-width: 420px">Permissões</th>
                            <th style="width:88px" class="text-end">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="rolesTableBody">
                        @forelse($roles as $role)
                            <tr data-enterprise-row="1" data-role-row="1" data-role-name="{{ \Illuminate\Support\Str::lower($role->name) }}">
                                <td>
                                    <span class="badge bg-dark-subtle text-dark border rounded-pill px-3 py-2 admin-roles-role-pill">{{ $role->name }}</span>
                                </td>
                                @if($isGlobalSuper ?? false)
                                    <td title="UUID: {{ $role->tenant_uuid ?? '—' }}">
                                        <span class="badge bg-light text-dark border">{{ $tenantSlugs[$role->tenant_uuid] ?? '—' }}</span>
                                    </td>
                                @endif
                                <td>
                                    <form method="POST" action="{{ route('admin.roles.permissions.update', $role->id) }}" id="adminRolePermForm{{ $role->id }}" class="d-flex flex-column gap-2">
                                        @csrf
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($permissions as $perm)
                                                <label class="form-check form-check-inline mb-0 border rounded-pill px-2 py-1 bg-white">
                                                    <input class="form-check-input"
                                                           type="checkbox"
                                                           name="permissions[]"
                                                           value="{{ $perm->name }}"
                                                           {{ $role->hasPermissionTo($perm->name) ? 'checked' : '' }}>
                                                    <span class="form-check-label">{{ $perm->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown admin-table-actions-dropdown">
                                        <button class="btn btn-sm admin-table-actions-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open actions">
                                            <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end admin-table-actions-menu">
                                            <li>
                                                <button type="submit" form="adminRolePermForm{{ $role->id }}" class="dropdown-item admin-table-action-item">
                                                    <i class="bi bi-save" aria-hidden="true"></i>
                                                    <span>Salvar</span>
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($isGlobalSuper ?? false) ? 4 : 3 }}" class="text-center py-4">
                                    <div class="admin-table-empty">
                                        <div class="admin-table-empty-title">Nenhum perfil encontrado</div>
                                        <div class="small text-muted">Crie ou sincronize perfis para editar permissões.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const search = document.getElementById('rolesSearchInput')
    const rows = Array.from(document.querySelectorAll('tr[data-role-row="1"]'))

    if (search) {
        search.addEventListener('input', () => {
            const q = String(search.value || '').trim().toLowerCase()
            rows.forEach((row) => {
                const name = String(row.getAttribute('data-role-name') || '')
                row.classList.toggle('d-none', q !== '' && !name.includes(q))
            })
        })
    }
})()
</script>
@endsection
