@extends('layouts.app')

@section('title','Admin — Clientes')
@section('page-title','Admin')

@section('content')
@php
    $totalUsers = $tenantUsers->total();
    $activeCount = $tenantUsers->getCollection()->where('status', 'active')->count();
    $invitedCount = $tenantUsers->getCollection()->where('status', 'invited')->count();
    $disabledCount = $tenantUsers->getCollection()->where('status', 'disabled')->count();
@endphp

<div class="admin-tenant-users-page">
    <x-admin.page-header
        title="Clientes"
        subtitle="Gerencie usuários operacionais, convites e status de acesso."
    >
        <x-slot:actions>
            @if(($isGlobalSuper ?? false) && ($tenants ?? collect())->count())
                <form method="GET" action="{{ url('/admin/customers') }}" class="d-flex align-items-center gap-2">
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
            <button type="button" class="btn btn-dark btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#tenantUserCreateModal" data-tenant-user-create-open="1">Adicionar usuário</button>
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#tenantGuestInviteModal">Adicionar convidado</button>
            <a href="{{ url('/admin/customers/user-groups') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Grupos de Clientes</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($tenant)
        <div class="admin-tenant-users-tenant-pill mb-3">
            <div class="grade-profile-field-box admin-tenant-users-tenant-box">
                <span class="grade-profile-field-kicker">Cliente</span>
                <div class="admin-tenant-users-tenant-main">
                    <strong>{{ $tenant->name }}</strong>
                    <code>{{ $tenant->uuid }}</code>
                </div>
            </div>
        </div>
    @endif

    @if(session('status'))
        <div class="alert alert-success admin-tenant-users-alert">{{ session('status') }}</div>
    @endif

    @if(session('tenant_invite_url'))
        <div class="alert alert-info admin-tenant-users-alert">
            <div class="fw-semibold mb-1">URL de convite gerada</div>
            <a href="{{ session('tenant_invite_url') }}" target="_blank" rel="noopener">{{ session('tenant_invite_url') }}</a>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger admin-tenant-users-alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm admin-tenant-users-metric">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-tenant-users-metric-box">
                        <span class="grade-profile-field-kicker">Usuários visíveis</span>
                        <div class="admin-tenant-users-metric-value">{{ $totalUsers }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm admin-tenant-users-metric">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-tenant-users-metric-box">
                        <span class="grade-profile-field-kicker">Ativos</span>
                        <div class="admin-tenant-users-metric-value">{{ $activeCount }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm admin-tenant-users-metric">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-tenant-users-metric-box">
                        <span class="grade-profile-field-kicker">Convidados</span>
                        <div class="admin-tenant-users-metric-value">{{ $invitedCount }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm admin-tenant-users-metric">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-tenant-users-metric-box">
                        <span class="grade-profile-field-kicker">Desativados</span>
                        <div class="admin-tenant-users-metric-value">{{ $disabledCount }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm admin-tenant-users-table-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Lista de clientes</strong>
            <span class="badge bg-light text-dark border rounded-pill">{{ $totalUsers }} registros</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 admin-tenant-users-table admin-enterprise-table">
                <thead class="table-light">
                    <tr>
                        <th style="width:72px">#</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        @if($isGlobalSuper ?? false)
                            <th>Cliente</th>
                        @endif
                        <th>Grupo</th>
                        <th>Plano</th>
                        <th style="width:140px">Status</th>
                        <th style="width:88px" class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenantUsers as $tenantUser)
                        @php
                            $displayName = $tenantUser->name ?: trim(($tenantUser->first_name ?? '') . ' ' . ($tenantUser->last_name ?? ''));
                            $parts = preg_split('/\\s+/', trim((string) $displayName)) ?: [];
                            $initials = collect($parts)->filter()->take(2)->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
                        @endphp
                        <tr data-enterprise-row="1">
                            <td>
                                <span class="admin-tenant-users-id-pill">#{{ $tenantUser->id }}</span>
                            </td>
                            <td>
                                <div class="admin-tenant-users-identity">
                                    <span class="admin-tenant-users-avatar">{{ $initials ?: 'U' }}</span>
                                    <div>
                                        <div class="fw-semibold">{{ $displayName ?: 'Sem nome' }}</div>
                                        <div class="small text-muted">{{ $tenantUser->username ? '@' . $tenantUser->username : 'sem username' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="admin-tenant-users-email">{{ $tenantUser->email }}</span>
                            </td>
                            @if($isGlobalSuper ?? false)
                                <td>
                                    <span class="badge bg-light text-dark border rounded-pill">
                                        {{ $tenantUser->tenant?->slug ?? $tenantUser->tenant?->name ?? $tenantUser->tenant_uuid }}
                                    </span>
                                </td>
                            @endif
                            <td>
                                <span class="badge bg-light text-dark border rounded-pill">{{ $tenantUser->group?->name ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border rounded-pill">{{ $tenantUser->pricePlan?->name ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="badge rounded-pill admin-tenant-users-status {{ $tenantUser->status === 'active' ? 'is-active' : ($tenantUser->status === 'disabled' ? 'is-disabled' : 'is-invited') }}">
                                    {{ $tenantUser->status === 'active' ? 'ativo' : ($tenantUser->status === 'disabled' ? 'desativado' : 'convidado') }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="dropdown admin-table-actions-dropdown">
                                    <button class="btn btn-sm admin-table-actions-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir ações">
                                        <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end admin-table-actions-menu">
                                        <li>
                                            <button
                                                class="dropdown-item admin-table-action-item"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#tenantUserCreateModal"
                                                data-tenant-user-edit="1"
                                                data-tenant-user-id="{{ $tenantUser->id }}"
                                                data-tenant-user-first-name="{{ e((string)($tenantUser->first_name ?? '')) }}"
                                                data-tenant-user-last-name="{{ e((string)($tenantUser->last_name ?? '')) }}"
                                                data-tenant-user-display-name="{{ e((string)($displayName ?? '')) }}"
                                                data-tenant-user-email="{{ e((string)$tenantUser->email) }}"
                                                data-tenant-user-username="{{ e((string)($tenantUser->username ?? '')) }}"
                                                data-tenant-user-status="{{ e((string)($tenantUser->status ?? 'active')) }}"
                                                data-tenant-user-timezone="{{ e((string)($tenantUser->timezone ?? 'America/Sao_Paulo')) }}"
                                                data-tenant-user-group-id="{{ e((string)($tenantUser->group_id ?? '')) }}"
                                                data-tenant-user-price-plan-id="{{ e((string)($tenantUser->price_plan_id ?? '')) }}"
                                            >
                                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                <span>Editar</span>
                                            </button>
                                        </li>
                                        <li>
                                            <button
                                                class="dropdown-item admin-table-action-item"
                                                type="button"
                                                data-tenant-user-notes="1"
                                                data-tenant-user-id="{{ $tenantUser->id }}"
                                                data-tenant-user-name="{{ e((string)($displayName ?: $tenantUser->email)) }}"
                                            >
                                                <i class="bi bi-journal-text" aria-hidden="true"></i>
                                                <span>Anotações</span>
                                            </button>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.tenantUsers.impersonate', $tenantUser->id) }}">
                                                @csrf
                                                <button class="dropdown-item admin-table-action-item" type="submit">
                                                    <i class="bi bi-person-bounding-box" aria-hidden="true"></i>
                                                    <span>Impersonar</span>
                                                </button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.tenantUsers.destroy', $tenantUser->id) }}" onsubmit="return confirm('Remover cliente?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="dropdown-item admin-table-action-item is-danger" type="submit">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                    <span>Excluir</span>
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($isGlobalSuper ?? false) ? 8 : 7 }}" class="text-center py-4">
                                <div class="admin-tenant-users-empty">
                                    <div class="admin-tenant-users-empty-title">Nenhum cliente encontrado</div>
                                    <div class="small text-muted">Crie um cliente ou envie um convite para preencher esta lista.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $tenantUsers->links() }}
        </div>
    </div>
</div>

<div class="modal fade" id="tenantUserCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1" id="tenantUserCreateModalTitle">Adicionar usuário</h5>
                    <p class="grade-modal-hint mb-0" id="tenantUserCreateModalHint">Crie uma conta operacional com credenciais completas.</p>
                </div>
            </div>
            <div class="modal-body pt-3">
                <form method="POST" action="{{ route('admin.tenantUsers.store') }}" id="tenantUserCreateForm" class="row g-2">
                    @csrf
                    <input type="hidden" id="tenantUserCreateFormMethod" name="_method" value="POST">
                    @if($isGlobalSuper ?? false)
                        <div class="col-12">
                            <label class="grade-field-box">
                                <span class="grade-field-kicker">Cliente</span>
                                <select name="tenant_uuid" id="tenantUserFormTenantUuid" class="grade-field-input grade-field-input--select" required>
                                    <option value="" @selected(empty($selectedTenantUuid)) disabled>Selecionar cliente</option>
                                    @foreach(($tenants ?? collect()) as $t)
                                        <option value="{{ $t->uuid }}" @selected((string)($selectedTenantUuid ?? '') === (string)$t->uuid)>
                                            {{ $t->name }} ({{ $t->slug ?? $t->uuid }})
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @else
                        <input type="hidden" name="tenant_uuid" value="{{ $tenant?->uuid }}">
                    @endif
                    <div class="col-md-4">
                        <label class="grade-field-box"><span class="grade-field-kicker">Nome</span><input class="grade-field-input" id="tenantUserFormFirstName" name="first_name" required></label>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box"><span class="grade-field-kicker">Sobrenome</span><input class="grade-field-input" id="tenantUserFormLastName" name="last_name"></label>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box"><span class="grade-field-kicker">E-mail</span><input class="grade-field-input" id="tenantUserFormEmail" type="email" name="email" required></label>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box"><span class="grade-field-kicker">Usuário</span><input class="grade-field-input" id="tenantUserFormUsername" name="username"></label>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box"><span class="grade-field-kicker">Senha</span><input class="grade-field-input" id="tenantUserFormPassword" type="password" name="password" required></label>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box"><span class="grade-field-kicker">Confirmar senha</span><input class="grade-field-input" id="tenantUserFormPasswordConfirmation" type="password" name="password_confirmation" required></label>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Grupo</span>
                            <select name="group_id" id="tenantUserFormGroupId" class="grade-field-input grade-field-input--select">
                                <option value="">Selecionar grupo</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}">
                                        @if($isGlobalSuper ?? false)
                                            {{ $group->tenant?->slug ?? $group->tenant?->name ?? $group->tenant_uuid }} ·
                                        @endif
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Plano comercial</span>
                            <select name="price_plan_id" id="tenantUserFormPricePlanId" class="grade-field-input grade-field-input--select">
                                <option value="">Selecionar plano de preço</option>
                                @foreach(($pricePlans ?? collect()) as $pricePlan)
                                    <option value="{{ $pricePlan->id }}">
                                        {{ $pricePlan->name ?: $pricePlan->code }}
                                        @if(!(bool) ($pricePlan->is_active ?? true)) (inativo) @endif
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box"><span class="grade-field-kicker">Fuso horário</span><input class="grade-field-input" id="tenantUserFormTimezone" name="timezone" value="America/Sao_Paulo"></label>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Status</span>
                            <select name="status" id="tenantUserFormStatus" class="grade-field-input grade-field-input--select">
                                <option value="active">ativo</option>
                                <option value="invited">convidado</option>
                                <option value="disabled">desativado</option>
                            </select>
                        </label>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-dark" id="tenantUserCreateSubmitBtn">Adicionar usuário</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tenantGuestInviteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1">Adicionar convidado</h5>
                    <p class="grade-modal-hint mb-0">Convide por e-mail e defina grupo inicial.</p>
                </div>
            </div>
            <div class="modal-body pt-3">
                <form method="POST" action="{{ route('admin.tenantUsers.invite') }}" class="row g-2">
                    @csrf
                    @if($isGlobalSuper ?? false)
                        <div class="col-12">
                            <label class="grade-field-box">
                                <span class="grade-field-kicker">Cliente</span>
                                <select name="tenant_uuid" class="grade-field-input grade-field-input--select" required>
                                    <option value="" @selected(empty($selectedTenantUuid)) disabled>Selecionar cliente</option>
                                    @foreach(($tenants ?? collect()) as $t)
                                        <option value="{{ $t->uuid }}" @selected((string)($selectedTenantUuid ?? '') === (string)$t->uuid)>
                                            {{ $t->name }} ({{ $t->slug ?? $t->uuid }})
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @else
                        <input type="hidden" name="tenant_uuid" value="{{ $tenant?->uuid }}">
                    @endif
                    <div class="col-md-6">
                        <label class="grade-field-box"><span class="grade-field-kicker">Nome</span><input class="grade-field-input" name="first_name"></label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box"><span class="grade-field-kicker">Sobrenome</span><input class="grade-field-input" name="last_name"></label>
                    </div>
                    <div class="col-12">
                        <label class="grade-field-box"><span class="grade-field-kicker">E-mail</span><input class="grade-field-input" type="email" name="email" required></label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box">
                            <span class="grade-field-kicker">Grupo</span>
                            <select name="group_id" class="grade-field-input grade-field-input--select">
                                <option value="">Selecionar grupo</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}">
                                        @if($isGlobalSuper ?? false)
                                            {{ $group->tenant?->slug ?? $group->tenant?->name ?? $group->tenant_uuid }} ·
                                        @endif
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="grade-field-box"><span class="grade-field-kicker">Expira em (horas)</span><input class="grade-field-input" type="number" name="expires_in_hours" value="48" min="1" max="168"></label>
                    </div>
                    <div class="col-12">
                        <label class="form-check admin-tenant-users-checkline">
                            <input class="form-check-input" type="checkbox" name="send_email" value="1" checked>
                            <span class="form-check-label">Enviar e-mail de convite</span>
                        </label>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-dark">Adicionar convidado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tenantUserNotesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1">Anotações</h5>
                    <p class="grade-modal-hint mb-0" id="tenantUserNotesTitle">Usuário</p>
                </div>
            </div>
            <div class="modal-body pt-3">
                <input type="hidden" id="tenantUserNotesUserId">
                <label class="grade-field-box">
                    <span class="grade-field-kicker">Notas internas</span>
                    <textarea class="grade-field-input" id="tenantUserNotesText" rows="5" placeholder="Digite suas anotações internas..."></textarea>
                </label>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-dark" id="tenantUserNotesSaveBtn">Salvar notas</button>
            </div>
        </div>
    </div>
</div>

<script>
;(function () {
    function on(el, eventName, handler) {
        if (!el || typeof el.addEventListener !== 'function') { return; }
        el.addEventListener(eventName, handler);
    }

    function fill(el, value) {
        if (!el) { return; }
        el.value = value == null ? '' : String(value);
    }

    var createOpenButtons = Array.prototype.slice.call(document.querySelectorAll('[data-tenant-user-create-open="1"]'));
    var notesButtons = Array.prototype.slice.call(document.querySelectorAll('[data-tenant-user-notes="1"]'));

    var createModalEl = document.getElementById('tenantUserCreateModal');
    var notesModalEl = document.getElementById('tenantUserNotesModal');
    var createForm = document.getElementById('tenantUserCreateForm');
    var createFormMethod = document.getElementById('tenantUserCreateFormMethod');
    var createModalTitle = document.getElementById('tenantUserCreateModalTitle');
    var createModalHint = document.getElementById('tenantUserCreateModalHint');
    var createSubmitBtn = document.getElementById('tenantUserCreateSubmitBtn');

    var formFirstName = document.getElementById('tenantUserFormFirstName');
    var formLastName = document.getElementById('tenantUserFormLastName');
    var formEmail = document.getElementById('tenantUserFormEmail');
    var formUsername = document.getElementById('tenantUserFormUsername');
    var formPassword = document.getElementById('tenantUserFormPassword');
    var formPasswordConfirmation = document.getElementById('tenantUserFormPasswordConfirmation');
    var formGroupId = document.getElementById('tenantUserFormGroupId');
    var formPricePlanId = document.getElementById('tenantUserFormPricePlanId');
    var formTimezone = document.getElementById('tenantUserFormTimezone');
    var formStatus = document.getElementById('tenantUserFormStatus');

    var notesUserIdEl = document.getElementById('tenantUserNotesUserId');
    var notesTitleEl = document.getElementById('tenantUserNotesTitle');
    var notesTextEl = document.getElementById('tenantUserNotesText');
    var notesSaveBtn = document.getElementById('tenantUserNotesSaveBtn');

    var createModal = (createModalEl && window.bootstrap) ? new window.bootstrap.Modal(createModalEl) : null;
    var notesModal = (notesModalEl && window.bootstrap) ? new window.bootstrap.Modal(notesModalEl) : null;

    var storeUrl = @json(route('admin.tenantUsers.store'));
    var editUrlTemplate = @json(route('admin.tenantUsers.update', ['id' => '__ID__']));

    function setCreateMode() {
        if (!createForm) { return; }
        createForm.action = storeUrl;
        if (createFormMethod) { createFormMethod.value = 'POST'; }
        if (createModalTitle) { createModalTitle.textContent = 'Adicionar usuário'; }
        if (createModalHint) { createModalHint.textContent = 'Crie uma conta operacional com credenciais completas.'; }
        if (createSubmitBtn) { createSubmitBtn.textContent = 'Adicionar usuário'; }

        fill(formFirstName, '');
        fill(formLastName, '');
        fill(formEmail, '');
        fill(formUsername, '');
        fill(formPassword, '');
        fill(formPasswordConfirmation, '');
        fill(formGroupId, '');
        fill(formPricePlanId, '');
        fill(formTimezone, 'America/Sao_Paulo');
        fill(formStatus, 'active');

        if (formPassword) { formPassword.required = true; }
        if (formPasswordConfirmation) { formPasswordConfirmation.required = true; }
    }

    function setEditMode(btn) {
        var userId = String(btn.getAttribute('data-tenant-user-id') || '').trim();
        if (!userId || !createForm) { return; }

        createForm.action = editUrlTemplate.replace('__ID__', encodeURIComponent(userId));
        if (createFormMethod) { createFormMethod.value = 'PUT'; }
        if (createModalTitle) { createModalTitle.textContent = 'Editar usuário'; }
        if (createModalHint) { createModalHint.textContent = 'Atualize os dados do usuário operacional.'; }
        if (createSubmitBtn) { createSubmitBtn.textContent = 'Salvar alterações'; }

        var firstName = String(btn.getAttribute('data-tenant-user-first-name') || '').trim();
        var lastName = String(btn.getAttribute('data-tenant-user-last-name') || '').trim();
        if (!firstName && !lastName) {
            var displayName = String(btn.getAttribute('data-tenant-user-display-name') || '').trim();
            if (displayName) {
                var parts = displayName.split(/\s+/).filter(Boolean);
                firstName = parts.shift() || '';
                lastName = parts.join(' ');
            }
        }

        fill(formFirstName, firstName);
        fill(formLastName, lastName);
        fill(formEmail, btn.getAttribute('data-tenant-user-email'));
        fill(formUsername, btn.getAttribute('data-tenant-user-username'));
        fill(formGroupId, btn.getAttribute('data-tenant-user-group-id'));
        fill(formPricePlanId, btn.getAttribute('data-tenant-user-price-plan-id'));
        fill(formTimezone, btn.getAttribute('data-tenant-user-timezone'));
        fill(formStatus, btn.getAttribute('data-tenant-user-status'));
        fill(formPassword, '');
        fill(formPasswordConfirmation, '');

        if (formPassword) { formPassword.required = false; }
        if (formPasswordConfirmation) { formPasswordConfirmation.required = false; }
    }

    createOpenButtons.forEach(function (btn) {
        on(btn, 'click', function () {
            setCreateMode();
        });
    });

    on(createModalEl, 'show.bs.modal', function (event) {
        var trigger = event && event.relatedTarget ? event.relatedTarget : null;
        if (trigger && trigger.matches && trigger.matches('[data-tenant-user-edit="1"]')) {
            setEditMode(trigger);
            return;
        }
        setCreateMode();
    });

    function notesKey(id) {
        return 'tenant-user-notes:' + id;
    }

    notesButtons.forEach(function (btn) {
        on(btn, 'click', function () {
            var userId = String(btn.getAttribute('data-tenant-user-id') || '').trim();
            var userName = String(btn.getAttribute('data-tenant-user-name') || 'Usuário');
            if (!userId) { return; }
            if (notesUserIdEl) { notesUserIdEl.value = userId; }
            if (notesTitleEl) { notesTitleEl.textContent = userName; }
            if (notesTextEl) { notesTextEl.value = localStorage.getItem(notesKey(userId)) || ''; }
            if (notesModal) { notesModal.show(); }
        });
    });

    on(notesSaveBtn, 'click', function () {
        var userId = String((notesUserIdEl && notesUserIdEl.value) || '').trim();
        if (!userId) { return; }
        localStorage.setItem(notesKey(userId), String((notesTextEl && notesTextEl.value) || ''));
        if (notesModal) { notesModal.hide(); }
    });

    Array.prototype.slice.call(document.querySelectorAll('.admin-table-actions-dropdown')).forEach(function (dropdownEl) {
        on(dropdownEl, 'show.bs.dropdown', function () {
            dropdownEl.classList.remove('dropup');
            var rect = dropdownEl.getBoundingClientRect();
            var estimatedMenuHeight = 220;
            var spaceBelow = window.innerHeight - rect.bottom;
            if (spaceBelow < estimatedMenuHeight) {
                dropdownEl.classList.add('dropup');
            }
        });
    });
})();
</script>
@endsection
