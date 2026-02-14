@extends('layouts.app')

@section('title','Admin — Usuários')
@section('page-title','Admin')

@section('content')
@php
    $totalUsers = $users->count();
    $activeUsers = $users->filter(fn($u) => empty($u->disabled_at))->count();
    $disabledUsers = $users->filter(fn($u) => !empty($u->disabled_at))->count();
    $adminUsers = $users->filter(fn($u) => $u->hasRole('admin'))->count();
    $activeTenants = $users->pluck('tenant_uuid')->filter()->unique()->count();
    $roleNames = $roles->pluck('name')->filter()->unique()->values();
    $roleNamesByTenant = collect($roleNamesByTenant ?? []);
    $tenantUserCounts = $users
        ->filter(fn($u) => !empty($u->tenant_uuid))
        ->groupBy('tenant_uuid')
        ->map(fn($group) => $group->count());
@endphp

<div class="admin-users-page">
	    <x-admin.page-header
        title="Usuários"
        subtitle="Gestão de acesso por perfil com edição rápida."
    >
        <x-slot:actions>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adminUserCreateModal">
                Adicionar usuário
            </button>
        </x-slot:actions>
    </x-admin.page-header>

	    @if(session('error'))
	        <div class="alert alert-warning mb-3">{{ session('error') }}</div>
	    @endif
	    @if($errors->any())
	        <div class="alert alert-danger mb-3">
	            <div class="fw-semibold">Nao foi possivel salvar</div>
	            <div class="small mt-1">
	                <ul class="mb-0">
	                    @foreach($errors->all() as $msg)
	                        <li>{{ $msg }}</li>
	                    @endforeach
	                </ul>
	            </div>
	        </div>
	    @endif
	    @if(session('status'))
	        <div class="alert alert-success mb-3">{{ session('status') }}</div>
	    @endif
    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-metric-field-box">
                        <span class="grade-profile-field-kicker">Usuários (visíveis)</span>
                        <div class="admin-metric-field-value" id="usersVisibleCount" data-initial="{{ $activeUsers }}">{{ $activeUsers }}</div>
                        <div class="admin-metric-field-hint" id="usersCountsHint">
                            total: {{ $totalUsers }}@if($disabledUsers > 0) (desativados: {{ $disabledUsers }})@endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-metric-field-box">
                        <span class="grade-profile-field-kicker">Admins</span>
                        <div class="admin-metric-field-value">{{ $adminUsers }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-metric-field-box">
                        <span class="grade-profile-field-kicker">Contas ativas</span>
                        <div class="admin-metric-field-value">{{ $activeTenants }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="fw-semibold">Gestão de usuários</div>
            <div class="d-flex align-items-center gap-2">
                <select id="usersRoleFilter" class="form-select form-select-sm" style="min-width: 160px;">
                    <option value="">Todos os perfis</option>
                    @foreach($roleNames as $roleName)
                        <option value="{{ $roleName }}">{{ $roleName }}</option>
                    @endforeach
                </select>
                <label class="d-flex align-items-center gap-2 small text-muted mb-0">
                    <input type="checkbox" class="form-check-input" id="usersShowDisabled">
                    Mostrar desativados
                </label>
                <input id="usersSearchInput" type="search" class="form-control form-control-sm" placeholder="Buscar por nome ou e-mail" style="min-width: 260px;">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 admin-users-table admin-enterprise-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:76px">#</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th style="width:180px">Nome de usuário</th>
                            <th style="width:180px">Grupo</th>
                            <th style="width:130px">Status</th>
                            <th style="width:170px">Data adicionado</th>
                            <th style="width:170px">Última atualização</th>
                            <th style="width:88px" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        @forelse($users as $u)
                            @php
                                $name = trim((string) $u->name);
                                $parts = preg_split('/\\s+/', $name) ?: [];
                                $initials = collect($parts)->filter()->take(2)->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');

                                $userRoleNames = $u->roles
                                    ->where('tenant_uuid', $u->tenant_uuid)
                                    ->pluck('name')
                                    ->unique()
                                    ->values();
                            @endphp
                            <tr
                                data-enterprise-row="1"
                                data-user-row="1"
                                data-name="{{ \Illuminate\Support\Str::lower($u->name) }}"
                                data-username="{{ \Illuminate\Support\Str::lower((string) ($u->username ?? '')) }}"
                                data-email="{{ \Illuminate\Support\Str::lower($u->email) }}"
                                data-disabled="{{ $u->disabled_at ? '1' : '0' }}"
                            >
                                @php
                                    $groupName = $u->is_super_admin ? 'superadmin' : ($userRoleNames->first() ?? '—');
                                    $extraGroupCount = max(0, $userRoleNames->count() - 1);
                                @endphp
                                <td><span class="admin-table-id-pill">#{{ $u->id }}</span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge rounded-pill bg-dark-subtle text-dark border">{{ $initials ?: 'U' }}</span>
                                        <div>
                                            <div class="fw-semibold d-flex flex-wrap gap-2 align-items-center">
                                                <span>{{ $u->name }}</span>
                                                @if($u->is_super_admin)
                                                    <span class="badge text-bg-dark">super admin</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span>{{ $u->email }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $u->username ?? '—' }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-light text-dark border">{{ $groupName }}</span>
                                        @if($extraGroupCount > 0)
                                            <span class="badge bg-light text-dark border">+{{ $extraGroupCount }}</span>
                                        @endif
                                        @foreach($userRoleNames as $roleName)
                                            <span class="d-none" data-user-role="{{ $roleName }}">{{ $roleName }}</span>
                                        @endforeach
                                        @if($u->is_super_admin)
                                            <span class="d-none" data-user-role="superadmin">superadmin</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($u->disabled_at)
                                        <span class="badge admin-table-status is-danger">desativado</span>
                                    @else
                                        <span class="badge admin-table-status is-success">ativo</span>
                                    @endif
                                </td>
                                <td>
                                    <span>{{ optional($u->created_at)->format('d/m/Y H:i') ?? '—' }}</span>
                                </td>
                                <td>
                                    <span>{{ optional($u->updated_at)->format('d/m/Y H:i') ?? '—' }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown admin-table-actions-dropdown">
                                        <button class="btn btn-sm admin-table-actions-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open actions">
                                            <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end admin-table-actions-menu">
                                            <li>
                                                <button
                                                    class="dropdown-item admin-table-action-item"
                                                    type="button"
                                                    data-admin-user-edit="1"
                                                    data-user-id="{{ $u->id }}"
                                                    data-user-name="{{ e($u->name) }}"
                                                    data-user-username="{{ e((string) ($u->username ?? '')) }}"
                                                    data-user-email="{{ e($u->email) }}"
                                                    data-user-locale="{{ e((string) ($u->locale ?? 'en')) }}"
                                                    data-user-timezone="{{ e((string) ($u->timezone ?? 'America/Sao_Paulo')) }}"
                                                    data-user-status="{{ $u->disabled_at ? 'disabled' : 'active' }}"
                                                    data-user-verified="{{ $u->email_verified_at ? '1' : '0' }}"
                                                    data-user-is-super-admin="{{ $u->is_super_admin ? '1' : '0' }}"
                                                    title="Editar dados"
                                                >
                                                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                    <span>Editar</span>
                                                </button>
                                            </li>
                                            <li>
                                                <button
                                                    class="dropdown-item admin-table-action-item"
                                                    type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#usersRolesCollapse{{ $u->id }}"
                                                    aria-expanded="false"
                                                    aria-controls="usersRolesCollapse{{ $u->id }}"
                                                    title="Gerenciar perfis"
                                                >
                                                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                                                    <span>Perfis</span>
                                                </button>
                                            </li>

                                            @if(auth()->user()->isSuperAdmin())
                                                <li>
                                                    <form method="POST" action="{{ route('admin.users.impersonate', $u->id) }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item admin-table-action-item">
                                                            <i class="bi bi-person-bounding-box" aria-hidden="true"></i>
                                                            <span>Impersonar</span>
                                                        </button>
                                                    </form>
                                                </li>

                                                @unless($u->is_super_admin || $u->hasRole('admin', $u->tenant_uuid))
                                                    <li>
                                                        <button
                                                            class="dropdown-item admin-table-action-item"
                                                            type="button"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#usersPromoteCollapse{{ $u->id }}"
                                                            aria-expanded="false"
                                                            aria-controls="usersPromoteCollapse{{ $u->id }}"
                                                        >
                                                            <i class="bi bi-arrow-up-circle" aria-hidden="true"></i>
                                                            <span>Promover</span>
                                                        </button>
                                                    </li>
                                                @endunless
                                            @endif

                                            @if(!$u->is_super_admin && auth()->id() !== $u->id)
                                                @if($u->disabled_at)
                                                    <li>
                                                        <form method="POST" action="{{ route('admin.users.enable', $u->id) }}">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item admin-table-action-item">
                                                                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                                                                <span>Reativar</span>
                                                            </button>
                                                        </form>
                                                    </li>
                                                @else
                                                    <li>
                                                        <form method="POST" action="{{ route('admin.users.disable', $u->id) }}" onsubmit="return confirm('Desativar este usuário? Ele não conseguirá mais entrar.')">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item admin-table-action-item">
                                                                <i class="bi bi-pause-circle" aria-hidden="true"></i>
                                                                <span>Desativar</span>
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif

                                                @php
                                                    $tUuid = (string) ($u->tenant_uuid ?? '');
                                                    $tSlug = (string) ($u->conta->slug ?? '');
                                                    $tName = (string) ($u->conta->name ?? '');
                                                    $tKey = trim($tSlug) !== '' ? trim($tSlug) : trim($tUuid);
                                                    $isLastUserInTenant = $tUuid !== '' && ((int) ($tenantUserCounts->get($tUuid, 0)) === 1);
                                                @endphp
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button
                                                        type="button"
                                                        class="dropdown-item admin-table-action-item is-danger"
                                                        data-admin-user-delete="1"
                                                        data-user-id="{{ $u->id }}"
                                                        data-user-name="{{ e($u->name) }}"
                                                        data-user-email="{{ e($u->email) }}"
                                                        data-tenant-uuid="{{ e($tUuid) }}"
                                                        data-tenant-key="{{ e($tKey) }}"
                                                        data-tenant-name="{{ e($tName) }}"
                                                        data-is-last-user="{{ $isLastUserInTenant ? '1' : '0' }}"
                                                    >
                                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                                        <span>Excluir</span>
                                                    </button>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </td>
                            </tr>

                            <tr class="collapse" id="usersRolesCollapse{{ $u->id }}">
                                <td colspan="9" class="bg-white">
                                    <div class="p-3 border-top">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                            <div class="fw-semibold">Perfis do usuário</div>
                                            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#usersRolesCollapse{{ $u->id }}">Fechar</button>
                                        </div>
                                        @if($u->is_super_admin)
                                            <div class="alert alert-info mb-0">
                                                <div class="fw-semibold">Superadmin</div>
                                                <div class="small">
                                                    Esta conta tem acesso total. Perfis (roles) por conta nao se aplicam aqui.
                                                </div>
                                            </div>
                                        @else
                                            <form method="POST" action="{{ route('admin.users.roles.update', $u->id) }}" class="d-flex flex-column gap-2" data-admin-guard="global-readonly">
                                                @csrf
                                                <div class="d-flex flex-wrap gap-2">
                                                    @php
                                                        $tenantRoleNames = $roleNamesByTenant->get((string) ($u->tenant_uuid ?? ''), $roleNames);
                                                    @endphp
                                                    @foreach($tenantRoleNames as $roleName)
                                                        @php
                                                            $checked = $u->hasRole($roleName, $u->tenant_uuid);
                                                            $adminDisabled = $roleName === 'admin' && !auth()->user()->isSuperAdmin();
                                                        @endphp
                                                        <label class="grade-role-chip mb-0">
                                                            <input class="grade-role-chip-input" type="checkbox" name="roles[]" value="{{ $roleName }}" {{ $checked ? 'checked' : '' }} {{ $adminDisabled ? 'disabled' : '' }}>
                                                            <span class="grade-role-chip-label">{{ $roleName }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="btn btn-sm btn-primary" data-admin-submit-btn="1">Salvar</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#usersRolesCollapse{{ $u->id }}">Cancelar</button>
                                                </div>
                                                @if(!auth()->user()->isSuperAdmin())
                                                    <div class="text-muted small">Obs: apenas super admin pode atribuir o perfil <code>admin</code>.</div>
                                                @endif
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            @if(auth()->user()->isSuperAdmin() && !$u->is_super_admin && !$u->hasRole('admin', $u->tenant_uuid))
                                <tr class="collapse" id="usersPromoteCollapse{{ $u->id }}">
                                    <td colspan="9" class="bg-white">
                                        <div class="p-3 border-top">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                                <div class="fw-semibold">Promover para admin</div>
                                                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#usersPromoteCollapse{{ $u->id }}">Fechar</button>
                                            </div>
                                            <form method="POST" action="{{ route('admin.users.promote', $u->id) }}" class="d-flex flex-wrap gap-2 align-items-center">
                                                @csrf
                                                <select name="plan" class="form-select form-select-sm" style="max-width: 220px;">
                                                    <option value="starter">Starter</option>
                                                    <option value="pro">Pro</option>
                                                    <option value="free">Free</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Confirmar promoção</button>
                                                <span class="text-muted small">Também ajusta o plano da conta.</span>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr id="usersEmptyRow">
                                <td colspan="9" class="text-center py-4">
                                    <div class="admin-table-empty">
                                        <div class="admin-table-empty-title">Nenhum usuário encontrado</div>
                                        <div class="small text-muted">Adicione usuários ou convide uma conta para começar.</div>
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

<div class="modal fade" id="adminUserCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1">Adicionar usuário</h5>
                    <p class="grade-modal-hint mb-0">Crie um usuário de sistema alinhado ao cadastro base (nome, e-mail, nome de usuário e verificação).</p>
                    <p class="grade-modal-hint mb-0">Conta definida automaticamente pelo seu usuário admin.</p>
                </div>
            </div>
            <div class="modal-body pt-3">
                <form method="POST" action="{{ route('admin.users.store') }}" id="adminUserCreateForm" class="row g-3">
                    @csrf
                    <div class="col-12 d-flex justify-content-end">
                        <div class="text-end">
                            <span class="grade-field-kicker d-block mb-2">Status</span>
                            <input type="hidden" name="status" id="adminUserCreateStatusValue" value="active">
                            <div class="form-check form-switch d-inline-flex align-items-center gap-2 mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="adminUserCreateStatusSwitch" checked>
                                <label class="form-check-label" for="adminUserCreateStatusSwitch">
                                    <span id="adminUserCreateStatusLabel">ON</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Nome</span>
                            <input type="text" name="name" class="grade-field-input" required maxlength="120" autocomplete="name">
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Nome de usuário</span>
                            <input type="text" name="username" class="grade-field-input" maxlength="64" autocomplete="username" placeholder="Opcional (gera a partir do e-mail)">
                        </label>
                        <div class="grade-modal-hint small mt-1">Apenas letras/números e <code>._-</code> (único no sistema).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">E-mail</span>
                            <input type="email" name="email" class="grade-field-input" required maxlength="190" autocomplete="email">
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Confirmar e-mail</span>
                            <input type="email" name="email_confirmation" class="grade-field-input" required maxlength="190" autocomplete="email">
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Senha</span>
                            <input type="password" name="password" class="grade-field-input" minlength="8" maxlength="190" autocomplete="new-password" required>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Confirmar senha</span>
                            <input type="password" name="password_confirmation" class="grade-field-input" minlength="8" maxlength="190" autocomplete="new-password" required>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Idioma</span>
                            <input type="text" name="locale" class="grade-field-input" maxlength="10" value="en" placeholder="en">
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Fuso horário</span>
                            <input type="text" name="timezone" class="grade-field-input" maxlength="64" value="America/Sao_Paulo" placeholder="America/Sao_Paulo">
                        </label>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark" id="adminUserCreateSubmitBtn">Criar usuário</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adminUserEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1">Editar usuário</h5>
                    <p class="grade-modal-hint mb-0">Atualize os campos base: nome, e-mail, nome de usuário, idioma, fuso, senha e status.</p>
                </div>
            </div>
            <div class="modal-body pt-3">
                <form method="POST" action="" id="adminUserEditForm" class="row g-3">
                    @csrf
                    @method('PUT')
                    <div class="col-12 d-flex justify-content-end">
                        <div class="text-end">
                            <span class="grade-field-kicker d-block mb-2">Status</span>
                            <input type="hidden" name="status" id="adminUserEditStatusValue" value="active">
                            <div class="form-check form-switch d-inline-flex align-items-center gap-2 mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="adminUserEditStatusSwitch" checked>
                                <label class="form-check-label" for="adminUserEditStatusSwitch">
                                    <span id="adminUserEditStatusLabel">ON</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Nome</span>
                            <input type="text" name="name" class="grade-field-input" id="adminUserEditName" required maxlength="120" autocomplete="name">
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Nome de usuário</span>
                            <input type="text" name="username" class="grade-field-input" id="adminUserEditUsername" maxlength="64" autocomplete="username">
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">E-mail</span>
                            <input type="email" name="email" class="grade-field-input" id="adminUserEditEmail" required maxlength="190" autocomplete="email">
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Idioma</span>
                            <input type="text" name="locale" class="grade-field-input" id="adminUserEditLocale" maxlength="10" placeholder="en">
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Nova senha</span>
                            <input type="password" name="password" class="grade-field-input" id="adminUserEditPassword" minlength="8" maxlength="190" autocomplete="new-password" placeholder="Deixe vazio para não alterar">
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Confirmar nova senha</span>
                            <input type="password" name="password_confirmation" class="grade-field-input" id="adminUserEditPasswordConfirmation" minlength="8" maxlength="190" autocomplete="new-password" placeholder="Repita a nova senha">
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Fuso horário</span>
                            <input type="text" name="timezone" class="grade-field-input" id="adminUserEditTimezone" maxlength="64" placeholder="America/Sao_Paulo">
                        </label>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <label class="form-check mb-0">
                            <input type="checkbox" class="form-check-input" name="email_verified" value="1" id="adminUserEditVerified">
                            <span class="form-check-label">Verificação: marcar como verificado</span>
                        </label>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark" id="adminUserEditSubmitBtn">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adminUserDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1">Excluir usuário</h5>
                    <p class="grade-modal-hint mb-0">Ação permanente. Pode deixar tenant órfão se for o último usuário.</p>
                </div>
            </div>
            <div class="modal-body pt-3">
                <div class="alert alert-danger">
                    <div class="fw-semibold">Confirmação necessária</div>
                    <div class="small" id="adminUserDeleteSummary">
                        Excluir este usuário permanentemente.
                    </div>
                </div>

                <form method="POST" action="" id="adminUserDeleteForm" class="d-flex flex-column gap-3">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="delete_tenant" id="adminUserDeleteTenantFlag" value="0">

                    <div id="adminUserDeleteTenantBlock" class="grade-modal-section d-none">
                        <div class="fw-semibold mb-1">Excluir conta e todos os dados</div>
                        <div class="small grade-modal-hint mb-2">
                            Isso só é possível com confirmação forte (checkbox + digitar o slug/uuid).
                        </div>

                        <label class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="confirm_delete_tenant" value="1" id="adminUserDeleteTenantAck">
                            <span class="form-check-label">
                                Entendi que isso vai apagar a conta (tenant) e todos os dados dessa conta.
                            </span>
                        </label>

                        <label class="grade-field-box grade-field-box--compact mb-0">
                            <span class="grade-field-kicker">
                                Digite o slug ou UUID da conta para confirmar:
                                <code id="adminUserDeleteTenantKeyHint"></code>
                            </span>
                            <input type="text" class="grade-field-input" name="confirm_delete_tenant_text" id="adminUserDeleteTenantText" placeholder="Ex: workspace-principal ou 03ec6b3d-...">
                        </label>
                        <div class="grade-modal-hint small mt-1">
                            Dica: o valor acima precisa bater exatamente (sem espaços).
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-outline-danger" id="adminUserDeleteOnlyBtn">Excluir somente usuário</button>
                        <button type="button" class="btn btn-danger d-none" id="adminUserDeleteWithTenantBtn" disabled>Excluir usuário + conta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div
	    id="adminUsersJsData"
	    class="d-none"
	    data-edit-action-template="{{ route('admin.users.update', ['id' => '__ID__']) }}"
        data-delete-action-template="{{ route('admin.users.destroy', ['id' => '__ID__']) }}"
	    data-can-assign-admin="{{ auth()->user()?->isSuperAdmin() ? '1' : '0' }}"
	    data-global-readonly="0"
	    data-total-users="{{ $totalUsers }}"
	    data-disabled-users="{{ $disabledUsers }}"
	></div>
<script>
(() => {
    const search = document.getElementById('usersSearchInput')
    const roleFilter = document.getElementById('usersRoleFilter')
    const showDisabledToggle = document.getElementById('usersShowDisabled')
    const rows = Array.from(document.querySelectorAll('tr[data-user-row="1"]'))
    const jsData = document.getElementById('adminUsersJsData')
    const visibleCountEl = document.getElementById('usersVisibleCount')
    const countsHintEl = document.getElementById('usersCountsHint')
    const totalUsers = Number(jsData?.dataset?.totalUsers || '0') || 0
    const disabledUsers = Number(jsData?.dataset?.disabledUsers || '0') || 0

    const applyFilters = () => {
        const q = String(search?.value || '').trim().toLowerCase()
        const role = String(roleFilter?.value || '').trim().toLowerCase()
        const showDisabled = !!showDisabledToggle?.checked

        rows.forEach((row) => {
            const name = String(row.getAttribute('data-name') || '')
            const username = String(row.getAttribute('data-username') || '')
            const email = String(row.getAttribute('data-email') || '')
            const matchesText = q === '' || name.includes(q) || username.includes(q) || email.includes(q)

            const roleBadges = Array.from(row.querySelectorAll('[data-user-role]'))
                .map((el) => String(el.getAttribute('data-user-role') || '').toLowerCase())
            const matchesRole = role === '' || roleBadges.includes(role)

            const isDisabled = String(row.getAttribute('data-disabled') || '0') === '1'
            const matchesDisabled = showDisabled ? true : !isDisabled

            row.classList.toggle('d-none', !(matchesText && matchesRole && matchesDisabled))
        })

        // Keep KPI aligned with what the table is currently showing (default hides disabled).
        const visibleRows = rows.filter((r) => !r.classList.contains('d-none')).length
        if (visibleCountEl) visibleCountEl.textContent = String(visibleRows)
        if (countsHintEl) {
            countsHintEl.textContent = `total: ${totalUsers}${disabledUsers > 0 ? ` (desativados: ${disabledUsers})` : ''}`
        }
    }

    if (search) search.addEventListener('input', applyFilters)
    if (roleFilter) roleFilter.addEventListener('change', applyFilters)
    if (showDisabledToggle) showDisabledToggle.addEventListener('change', applyFilters)

    // Edit modal population
    const editButtons = Array.from(document.querySelectorAll('[data-admin-user-edit="1"]'))
    const editModal = document.getElementById('adminUserEditModal')
    const editForm = document.getElementById('adminUserEditForm')
    const editName = document.getElementById('adminUserEditName')
    const editUsername = document.getElementById('adminUserEditUsername')
    const editEmail = document.getElementById('adminUserEditEmail')
    const editLocale = document.getElementById('adminUserEditLocale')
    const editTimezone = document.getElementById('adminUserEditTimezone')
    const editPassword = document.getElementById('adminUserEditPassword')
    const editPasswordConfirmation = document.getElementById('adminUserEditPasswordConfirmation')
    const createStatusSwitch = document.getElementById('adminUserCreateStatusSwitch')
    const createStatusValue = document.getElementById('adminUserCreateStatusValue')
    const createStatusLabel = document.getElementById('adminUserCreateStatusLabel')
    const editStatusSwitch = document.getElementById('adminUserEditStatusSwitch')
    const editStatusValue = document.getElementById('adminUserEditStatusValue')
    const editStatusLabel = document.getElementById('adminUserEditStatusLabel')
    const editVerified = document.getElementById('adminUserEditVerified')
    const editActionTemplate = String(jsData?.dataset?.editActionTemplate || '')
    const deleteActionTemplate = String(jsData?.dataset?.deleteActionTemplate || '')

    const syncStatusSwitch = (inputEl, hiddenEl, labelEl) => {
        if (!inputEl || !hiddenEl) return
        const isOn = !!inputEl.checked
        hiddenEl.value = isOn ? 'active' : 'disabled'
        if (labelEl) labelEl.textContent = isOn ? 'ON' : 'OFF'
    }

    if (createStatusSwitch) {
        createStatusSwitch.addEventListener('change', () => syncStatusSwitch(createStatusSwitch, createStatusValue, createStatusLabel))
        syncStatusSwitch(createStatusSwitch, createStatusValue, createStatusLabel)
    }

    if (editStatusSwitch) {
        editStatusSwitch.addEventListener('change', () => syncStatusSwitch(editStatusSwitch, editStatusValue, editStatusLabel))
        syncStatusSwitch(editStatusSwitch, editStatusValue, editStatusLabel)
    }

    editButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!editForm || !editModal) return
            const id = String(btn.getAttribute('data-user-id') || '').trim()
            editForm.action = editActionTemplate.replace('__ID__', encodeURIComponent(id))
            if (editName) editName.value = String(btn.getAttribute('data-user-name') || '')
            if (editUsername) editUsername.value = String(btn.getAttribute('data-user-username') || '')
            if (editEmail) editEmail.value = String(btn.getAttribute('data-user-email') || '')
            if (editLocale) editLocale.value = String(btn.getAttribute('data-user-locale') || 'en')
            if (editTimezone) editTimezone.value = String(btn.getAttribute('data-user-timezone') || 'America/Sao_Paulo')
            const statusValue = String(btn.getAttribute('data-user-status') || 'active')
            if (editStatusSwitch) editStatusSwitch.checked = statusValue === 'active'
            const isSuperAdmin = String(btn.getAttribute('data-user-is-super-admin') || '0') === '1'
            if (editStatusSwitch) {
                editStatusSwitch.disabled = isSuperAdmin
                if (isSuperAdmin) {
                    editStatusSwitch.checked = true
                }
            }
            syncStatusSwitch(editStatusSwitch, editStatusValue, editStatusLabel)
            if (editPassword) editPassword.value = ''
            if (editPasswordConfirmation) editPasswordConfirmation.value = ''
            if (editVerified) editVerified.checked = String(btn.getAttribute('data-user-verified') || '0') === '1'
            window.bootstrap?.Modal?.getOrCreateInstance(editModal)?.show()
        })
    })

    // Delete modal population (with strong confirm only if last user in tenant)
    const deleteButtons = Array.from(document.querySelectorAll('[data-admin-user-delete="1"]'))
    const deleteModal = document.getElementById('adminUserDeleteModal')
    const deleteForm = document.getElementById('adminUserDeleteForm')
    const deleteSummary = document.getElementById('adminUserDeleteSummary')
    const deleteTenantBlock = document.getElementById('adminUserDeleteTenantBlock')
    const deleteTenantAck = document.getElementById('adminUserDeleteTenantAck')
    const deleteTenantText = document.getElementById('adminUserDeleteTenantText')
    const deleteTenantKeyHint = document.getElementById('adminUserDeleteTenantKeyHint')
    const deleteTenantFlag = document.getElementById('adminUserDeleteTenantFlag')
    const deleteOnlyBtn = document.getElementById('adminUserDeleteOnlyBtn')
    const deleteWithTenantBtn = document.getElementById('adminUserDeleteWithTenantBtn')

    let currentTenantKey = ''
    const syncDeleteTenantUi = () => {
        const ack = !!deleteTenantAck?.checked
        const text = String(deleteTenantText?.value || '').trim()
        const canDeleteTenant = ack && text !== '' && currentTenantKey !== '' && text === currentTenantKey

        if (deleteTenantFlag) deleteTenantFlag.value = canDeleteTenant ? '1' : '0'
        if (deleteWithTenantBtn) deleteWithTenantBtn.disabled = !canDeleteTenant
    }

    deleteButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!deleteModal || !deleteForm) return

            const id = String(btn.getAttribute('data-user-id') || '').trim()
            const name = String(btn.getAttribute('data-user-name') || '').trim()
            const email = String(btn.getAttribute('data-user-email') || '').trim()
            const tenantUuid = String(btn.getAttribute('data-tenant-uuid') || '').trim()
            const tenantKey = String(btn.getAttribute('data-tenant-key') || '').trim()
            const tenantName = String(btn.getAttribute('data-tenant-name') || '').trim()
            const isLastUser = String(btn.getAttribute('data-is-last-user') || '0') === '1'

            deleteForm.action = deleteActionTemplate.replace('__ID__', encodeURIComponent(id))

            const tenantLabel = tenantName ? `${tenantName} (${tenantKey || tenantUuid || '—'})` : (tenantKey || tenantUuid || '—')
            if (deleteSummary) {
                deleteSummary.textContent = `Usuário: ${name || '—'} <${email || '—'}>.`
                    + (isLastUser ? ` Este é o último usuário da conta: ${tenantLabel}.` : '')
                    + ' Você pode excluir somente o usuário ou (com confirmação forte) excluir usuário + conta.'
            }

            currentTenantKey = tenantKey || tenantUuid
            if (deleteTenantKeyHint) deleteTenantKeyHint.textContent = currentTenantKey || '—'
            if (deleteTenantText) deleteTenantText.value = ''
            if (deleteTenantAck) deleteTenantAck.checked = false
            if (deleteTenantFlag) deleteTenantFlag.value = '0'

            if (deleteTenantBlock) deleteTenantBlock.classList.toggle('d-none', !isLastUser)
            if (deleteWithTenantBtn) deleteWithTenantBtn.classList.toggle('d-none', !isLastUser)
            syncDeleteTenantUi()

            window.bootstrap?.Modal?.getOrCreateInstance(deleteModal)?.show()
        })
    })

    if (deleteTenantAck) deleteTenantAck.addEventListener('change', syncDeleteTenantUi)
    if (deleteTenantText) deleteTenantText.addEventListener('input', syncDeleteTenantUi)
    if (deleteWithTenantBtn) {
        deleteWithTenantBtn.addEventListener('click', () => {
            // Requires strong confirm. Hidden flag is synced above.
            syncDeleteTenantUi()
            const can = String(deleteTenantFlag?.value || '0') === '1'
            if (!can || !deleteForm) return
            deleteForm.submit()
        })
    }

	    // Submit UX: show feedback to avoid "nothing happened" perception.
	    const wireSubmitState = (formId, btnId, busyText) => {
	        const form = document.getElementById(formId)
	        const btn = document.getElementById(btnId)
	        if (!form || !btn) return
	        form.addEventListener('submit', (ev) => {
	            btn.disabled = true
	            btn.dataset.originalText = btn.textContent || ''
	            btn.textContent = busyText
	        })
	    }
    wireSubmitState('adminUserCreateForm', 'adminUserCreateSubmitBtn', 'Criando...')
    wireSubmitState('adminUserEditForm', 'adminUserEditSubmitBtn', 'Salvando...')
    wireSubmitState('adminUserDeleteForm', 'adminUserDeleteOnlyBtn', 'Excluindo...')

    document.querySelectorAll('form[data-admin-guard="global-readonly"]').forEach((form) => {
        form.addEventListener('submit', (ev) => {
            const btn = form.querySelector('[data-admin-submit-btn="1"]')
            if (btn) {
                btn.disabled = true
                btn.dataset.originalText = btn.textContent || ''
                btn.textContent = 'Salvando...'
            }
        })
    })

    applyFilters()
})()
</script>
@endsection
