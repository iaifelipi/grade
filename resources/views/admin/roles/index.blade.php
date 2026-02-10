@extends('layouts.app')

@section('title','Admin — Perfis & Permissões')
@section('page-title','Admin')

@section('topbar-tools')
    <div class="explore-toolbar explore-toolbar--topbar">
        <div class="explore-toolbar-actions ms-auto">
            <div class="explore-chip-group">
                <div class="explore-filter-inline">
                    <div class="source-combo">
                        <div class="source-select-wrap">
                            <select id="rolesSourceSelect" class="form-select">
                                <option value="">Todos os arquivos</option>
                                @foreach($topbarSources as $source)
                                    <option value="{{ $source->id }}" @selected((int) $currentSourceId === (int) $source->id)>
                                        {{ $source->original_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <a href="{{ route('home') }}" class="btn btn-primary explore-add-source-btn" title="Abrir Explore" aria-label="Abrir Explore">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                    </div>
                </div>
                <div class="explore-filter-dropdown dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Filtros
                    </button>
                    <div class="dropdown-menu dropdown-menu-end explore-filter-menu p-3">
                        <label class="form-label small text-muted">Arquivo</label>
                        <select id="rolesSourceSelectMobile" class="form-select mb-2">
                            <option value="">Todos os arquivos</option>
                            @foreach($topbarSources as $source)
                                <option value="{{ $source->id }}" @selected((int) $currentSourceId === (int) $source->id)>
                                    {{ $source->original_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
@php
    $totalRoles = $roles->count();
    $totalPermissions = $permissions->count();
    $scopedTenants = $roles->pluck('tenant_uuid')->filter()->unique()->count();
@endphp
<div class="container-fluid py-4 admin-roles-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Perfis & Permissões</h2>
            <div class="text-muted small">Governança de acesso com edição por perfil.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Usuários</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Perfis</div>
                    <div class="fw-bold fs-5">{{ $totalRoles }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Permissões do sistema</div>
                    <div class="fw-bold fs-5">{{ $totalPermissions }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Contas com perfis</div>
                    <div class="fw-bold fs-5">{{ $scopedTenants }}</div>
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
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:160px">Perfil</th>
                            @if($isGlobalSuper ?? false)
                                <th style="width:180px">Conta</th>
                            @endif
                            <th style="min-width: 420px">Permissões</th>
                            <th style="width:140px">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="rolesTableBody">
                        @forelse($roles as $role)
                            <tr data-role-row="1" data-role-name="{{ \Illuminate\Support\Str::lower($role->name) }}">
                                <td>
                                    <span class="badge bg-dark-subtle text-dark border rounded-pill px-3 py-2">{{ $role->name }}</span>
                                </td>
                                @if($isGlobalSuper ?? false)
                                    <td title="UUID: {{ $role->tenant_uuid ?? '—' }}">
                                        <span class="badge bg-light text-dark border">{{ $tenantSlugs[$role->tenant_uuid] ?? '—' }}</span>
                                    </td>
                                @endif
                                <td>
                                    <form method="POST" action="{{ route('admin.roles.permissions.update', $role->id) }}" class="d-flex flex-column gap-2">
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
                                        <div>
                                            <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                                        </div>
                                    </form>
                                </td>
                                <td class="text-end text-muted small">Atualização manual</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($isGlobalSuper ?? false) ? 4 : 3 }}" class="text-center text-muted py-4">Nenhum perfil encontrado</td>
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
    const select = document.getElementById('rolesSourceSelect')
    const selectMobile = document.getElementById('rolesSourceSelectMobile')
    const search = document.getElementById('rolesSearchInput')
    const rows = Array.from(document.querySelectorAll('tr[data-role-row="1"]'))

    const selectTemplate = @json(route('vault.explore.selectSource', ['id' => '__ID__']))
    const clearUrl = @json(route('vault.explore.clearSource'))

    const navigate = (value) => {
        const id = String(value || '').trim()
        if (!id) {
            window.location.href = clearUrl
            return
        }
        window.location.href = selectTemplate.replace('__ID__', encodeURIComponent(id))
    }

    if (select) {
        select.addEventListener('change', () => {
            if (selectMobile) selectMobile.value = select.value
            navigate(select.value)
        })
    }
    if (selectMobile) {
        selectMobile.addEventListener('change', () => {
            if (select) select.value = selectMobile.value
            navigate(selectMobile.value)
        })
    }

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
