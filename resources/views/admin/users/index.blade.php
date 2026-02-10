@extends('layouts.app')

@section('title','Admin — Usuários')
@section('page-title','Admin')

@section('topbar-tools')
    <div class="explore-toolbar explore-toolbar--topbar">
        <div class="explore-toolbar-actions ms-auto">
            <div class="explore-chip-group">
                <div class="explore-filter-inline">
                    <div class="source-combo">
                        <div class="source-select-wrap">
                            <select id="usersSourceSelect" class="form-select">
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
                        <select id="usersSourceSelectMobile" class="form-select mb-2">
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
    $totalUsers = $users->count();
    $adminUsers = $users->filter(fn($u) => $u->hasRole('admin'))->count();
    $activeTenants = $users->pluck('tenant_uuid')->filter()->unique()->count();
@endphp
<div class="container-fluid py-4 admin-users-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Usuários</h2>
            <div class="text-muted small">Gestão de acesso por perfil com edição rápida.</div>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Usuários</div>
                    <div class="fw-bold fs-5">{{ $totalUsers }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Admins</div>
                    <div class="fw-bold fs-5">{{ $adminUsers }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Contas ativas</div>
                    <div class="fw-bold fs-5">{{ $activeTenants }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="fw-semibold">Gestão de usuários</div>
            <div class="d-flex align-items-center gap-2">
                <input id="usersSearchInput" type="search" class="form-control form-control-sm" placeholder="Buscar por nome ou e-mail" style="min-width: 260px;">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:76px">#</th>
                            <th>Usuário</th>
                            @if($isGlobalSuper ?? false)
                                <th style="width:180px">Conta</th>
                                <th style="width:120px">Plano</th>
                            @endif
                            <th style="min-width: 380px">Perfis</th>
                            <th style="width:260px">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        @forelse($users as $u)
                            @php
                                $name = trim((string) $u->name);
                                $parts = preg_split('/\s+/', $name) ?: [];
                                $initials = collect($parts)->filter()->take(2)->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
                            @endphp
                            <tr data-user-row="1" data-name="{{ \Illuminate\Support\Str::lower($u->name) }}" data-email="{{ \Illuminate\Support\Str::lower($u->email) }}">
                                <td>#{{ $u->id }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge rounded-pill bg-dark-subtle text-dark border">{{ $initials ?: 'U' }}</span>
                                        <div>
                                            <div class="fw-semibold">{{ $u->name }}</div>
                                            <div class="small text-muted">{{ $u->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                @if($isGlobalSuper ?? false)
                                    <td title="UUID: {{ $u->tenant_uuid ?? '—' }}">
                                        <span class="badge bg-light text-dark border">{{ $u->conta->slug ?? '—' }}</span>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ $u->conta->plan ?? '—' }}</span></td>
                                @endif
                                <td>
                                    <form method="POST" action="{{ route('admin.users.roles.update', $u->id) }}" class="d-flex flex-column gap-2">
                                        @csrf
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($roles as $r)
                                                <label class="form-check form-check-inline mb-0 border rounded-pill px-2 py-1 bg-white">
                                                    <input class="form-check-input"
                                                           type="checkbox"
                                                           name="roles[]"
                                                           value="{{ $r->name }}"
                                                           {{ $u->hasRole($r->name) ? 'checked' : '' }}>
                                                    <span class="form-check-label">{{ $r->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <div>
                                            <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                                        </div>
                                    </form>
                                </td>
                                <td>
                                    @if(auth()->user()->isSuperAdmin())
                                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                                            <form method="POST" action="{{ route('admin.users.impersonate', $u->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Impersonar</button>
                                            </form>
                                            @unless($u->hasRole('admin'))
                                                <form method="POST" action="{{ route('admin.users.promote', $u->id) }}" class="d-flex gap-1">
                                                    @csrf
                                                    <select name="plan" class="form-select form-select-sm">
                                                        <option value="starter">Starter</option>
                                                        <option value="pro">Pro</option>
                                                        <option value="free">Free</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Promover</button>
                                                </form>
                                            @endunless
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr id="usersEmptyRow">
                                <td colspan="{{ ($isGlobalSuper ?? false) ? 6 : 4 }}" class="text-center text-muted py-4">Nenhum usuário encontrado</td>
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
    const select = document.getElementById('usersSourceSelect')
    const selectMobile = document.getElementById('usersSourceSelectMobile')
    const search = document.getElementById('usersSearchInput')
    const rows = Array.from(document.querySelectorAll('tr[data-user-row="1"]'))

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
                const name = String(row.getAttribute('data-name') || '')
                const email = String(row.getAttribute('data-email') || '')
                row.classList.toggle('d-none', q !== '' && !name.includes(q) && !email.includes(q))
            })
        })
    }
})()
</script>
@endsection
