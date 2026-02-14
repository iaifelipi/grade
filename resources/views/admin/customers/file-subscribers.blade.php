@extends('layouts.app')

@section('title','Admin — Assinantes da Lista')
@section('page-title','Admin')

@section('content')
@php
    $fileRouteKey = (string) ($file->public_uid ?: $file->id);
    $listDisplayName = trim((string) ($listDisplayName ?? ($file->display_name ?: $file->original_name ?: 'Lista sem nome')));
    $customerDisplayName = trim((string) ($customerDisplayName ?? 'Cliente não identificado'));
    $formatBrPhone = static function (?string $value): string {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return $raw;
        }
        if (str_starts_with($digits, '0055')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '055')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '55') && strlen($digits) > 11) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) < 10) {
            return $raw;
        }
        $ddd = substr($digits, 0, 2);
        $number = substr($digits, 2);
        if (strlen($number) === 9) {
            return '+55 (' . $ddd . ') ' . substr($number, 0, 5) . '-' . substr($number, 5);
        }
        if (strlen($number) === 8) {
            return '+55 (' . $ddd . ') ' . substr($number, 0, 4) . '-' . substr($number, 4);
        }
        return '+55 (' . $ddd . ') ' . $number;
    };
    $formatBirthDate = static function (?string $value): string {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            try {
                return \Illuminate\Support\Carbon::parse($raw)->format('d/m/Y');
            } catch (\Throwable) {
                return $raw;
            }
        }
        return $raw;
    };
@endphp
<div class="admin-tenant-users-page">
    <style>
        .customer-context-banner {
            border: 1px solid #b9e9d1;
            background: #e8faf1;
            color: #176a45;
            border-radius: 12px;
            padding: 10px 12px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        .customer-context-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #d8c7aa;
            background: #f6efe4;
            color: #7b5e32;
            border-radius: 999px;
            padding: 2px 10px;
            font-size: 12px;
            font-weight: 600;
        }
        .subscribers-toolbar {
            border: 1px solid #eadcc8;
            border-radius: 12px;
            background: #fffaf3;
            padding: 10px;
            margin-bottom: 10px;
        }
        .subscribers-columns-menu {
            min-width: 220px;
            padding: 8px 10px;
        }
        .subscribers-columns-menu .form-check {
            margin-bottom: 6px;
        }
        .subscribers-columns-menu .form-check:last-child {
            margin-bottom: 0;
        }
        .subscribers-columns-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 8px 12px;
        }
        .subscriber-profile-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 12px;
        }
        .subscriber-profile-top {
            border: 1px solid #eadcc8;
            border-radius: 12px;
            background: #fffaf3;
            padding: 12px;
        }
        .subscriber-profile-top .fw-semibold {
            color: #4c3418;
            font-weight: 800 !important;
        }
        .subscriber-profile-top .text-muted {
            color: #6b4f2a !important;
            font-weight: 600;
        }
        .subscriber-profile-avatar {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            border: 1px solid #eadcc8;
            background: #f7efe3;
            color: #7b5e32;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .subscriber-profile-section {
            border: 1px solid #eadcc8;
            border-radius: 12px;
            background: #fff;
            padding: 10px;
            margin-bottom: 10px;
        }
        .subscriber-profile-section-title {
            font-size: 12px;
            font-weight: 700;
            color: #6a4d28;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: 8px;
        }
        .subscriber-profile-section .grade-profile-field-box {
            border-color: #e1ceb1;
            background: #fffdf9;
        }
        .subscriber-profile-section .grade-profile-field-kicker {
            color: #6f522c;
            font-weight: 700;
        }
        .subscriber-profile-section .grade-profile-field-box > div {
            color: #2f1f0f;
            font-weight: 600;
            word-break: break-word;
        }
        .admin-enterprise-table tbody td {
            color: #2f1f0f;
            font-weight: 500;
        }
        .admin-enterprise-table tbody td a {
            color: #3f2c12;
            font-weight: 700;
        }
        .admin-enterprise-table tbody td a:hover {
            color: #2f1f0f;
            text-decoration: underline !important;
        }
        .admin-tenant-users-table-card .card-header,
        .admin-tenant-users-table-card .card-footer {
            background: #fff8ef !important;
            border-color: #eadcc8 !important;
        }
        .admin-tenant-users-table-card .table > :not(caption) > * > * {
            border-color: #ecdac2;
        }
        .admin-tenant-users-table-card .table thead th {
            background: #f6ead8;
            color: #6c4f29;
            font-weight: 700;
        }
        .admin-tenant-users-table-card .table tbody td {
            background: #fffdf9;
        }
        .subscribers-bulk-toolbar {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            border: 1px solid #e3cfb0;
            border-radius: 12px;
            background: #fff8ee;
            padding: 8px 10px;
        }
        .subscribers-bulk-toolbar .form-select.form-select-sm {
            min-width: 250px;
            border-radius: 999px;
            font-weight: 600;
            color: #4b3417;
            border-color: #dcc5a4;
            background-color: #fffdf9;
        }
        .subscribers-bulk-count {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #6b4f2a;
            font-size: 12px;
            font-weight: 700;
            background: #f6ecd9;
            border: 1px solid #e0c9a7;
            border-radius: 999px;
            padding: 4px 10px;
        }
        .subscribers-bulk-scope {
            min-width: 220px;
        }
        .subscribers-bulk-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #6b4f2a;
            font-weight: 600;
            margin: 0;
        }
        .subscribers-bulk-toolbar .btn {
            border-radius: 999px;
            min-width: 84px;
        }
        .subscribers-bulk-status {
            margin: 0;
            width: 100%;
            font-size: 12px;
            color: #6b4f2a;
        }
        .subscribers-select-col {
            width: 46px;
            text-align: center;
        }
        .admin-tenant-users-table-card .pagination {
            --bs-pagination-color: #6a4d28;
            --bs-pagination-border-color: #dec8a7;
            --bs-pagination-hover-color: #3f2b12;
            --bs-pagination-hover-bg: #fff2e0;
            --bs-pagination-hover-border-color: #caa97a;
            --bs-pagination-focus-color: #3f2b12;
            --bs-pagination-focus-bg: #fff2e0;
            --bs-pagination-focus-box-shadow: 0 0 0 .2rem rgba(123, 94, 50, .2);
            --bs-pagination-active-color: #fff;
            --bs-pagination-active-bg: #7b5e32;
            --bs-pagination-active-border-color: #7b5e32;
            --bs-pagination-disabled-color: #a58a64;
            --bs-pagination-disabled-bg: #fffaf3;
            --bs-pagination-disabled-border-color: #eadcc8;
            gap: 4px;
        }
        .admin-tenant-users-table-card .pagination .page-link {
            border-radius: 999px;
            font-weight: 700;
            min-width: 34px;
            text-align: center;
            background: #fff;
        }
        .subscriber-profile-extras-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 10px;
        }
        .subscriber-profile-extras-item {
            border: 1px solid #e6d4ba;
            border-radius: 10px;
            background: #fffdf8;
            padding: 8px 10px;
        }
        .subscriber-profile-extras-item .k {
            color: #6b502c;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .03em;
            margin-bottom: 2px;
        }
        .subscriber-profile-extras-item .v {
            color: #2f1f0f;
            font-size: 13px;
            font-weight: 600;
            word-break: break-word;
        }
        #subscriberProfileAccordion .accordion-button {
            color: #4f3518;
            font-weight: 700;
            background: #fff8ef;
        }
        #subscriberProfileAccordion .accordion-button:not(.collapsed) {
            background: #fff3e3;
            color: #3f2b12;
            box-shadow: none;
        }
        #subscriberProfileAccordion .accordion-body {
            background: #fff;
        }
        .subscribers-header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }
        .subscribers-header-actions .btn {
            white-space: nowrap;
        }
        @media (max-width: 768px) {
            .subscriber-profile-grid {
                grid-template-columns: 1fr;
            }
            .subscriber-profile-extras-list {
                grid-template-columns: 1fr;
            }
            .subscribers-header-actions {
                justify-content: flex-start;
                width: 100%;
            }
            .subscribers-header-actions .btn {
                flex: 1 1 calc(50% - 8px);
                min-width: 160px;
                text-align: center;
            }
        }
        @media (max-width: 575.98px) {
            .subscribers-header-actions .btn {
                flex: 1 1 100%;
                min-width: 100%;
            }
        }
    </style>

    <x-admin.page-header
        title="Lista de Assinantes"
        subtitle="Visão administrativa em contexto de cliente, sem impersonação de sessão."
    >
        <x-slot:actions>
            <div class="subscribers-header-actions">
                <span class="customer-context-badge">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    Contexto do cliente
                </span>
                <button class="btn btn-outline-secondary btn-sm rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#subscribersFiltersPanel" aria-expanded="false" aria-controls="subscribersFiltersPanel">
                    Filtros
                </button>
                <button class="btn btn-outline-secondary btn-sm rounded-pill" type="button" data-bs-toggle="modal" data-bs-target="#subscribersColumnsModal">
                    Colunas
                </button>
                <a href="{{ route('admin.customers.files.subscribersExport', array_filter(['id' => $fileRouteKey, 'q' => $search, 'score_min' => $scoreMin], static fn ($v): bool => $v !== null && $v !== '')) }}"
                   class="btn btn-outline-secondary btn-sm rounded-pill">
                    Exportar CSV
                </a>
                <a href="{{ route('admin.customers.files.show', ['id' => $fileRouteKey]) }}" class="btn btn-outline-secondary btn-sm rounded-pill">Voltar aos detalhes</a>
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('status'))
        <div class="alert alert-success admin-tenant-users-alert">{{ session('status') }}</div>
    @endif

    <div class="collapse mb-2 @if($search !== '' || $scoreMin !== '' || (int) $perPage !== 20) show @endif" id="subscribersFiltersPanel">
        <div class="subscribers-toolbar">
            <form method="GET" action="{{ route('admin.customers.files.subscribers', ['id' => $fileRouteKey]) }}" class="d-flex align-items-center gap-2 flex-wrap">
                @if($isGlobalSuper)
                    <input type="hidden" name="tenant_uuid" value="{{ $tenantUuid }}">
                @endif
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Buscar por nome, e-mail, telefone, WhatsApp, cidade, UF, tipo ou estágio" style="min-width:340px;">
                <input type="number" name="score_min" value="{{ $scoreMin }}" min="0" max="100" class="form-control form-control-sm" placeholder="Score mín." style="width:120px;">
                <select name="per_page" class="form-select form-select-sm" style="width:170px;">
                    @foreach($perPageAllowed as $option)
                        <option value="{{ $option }}" @selected((int) $perPage === (int) $option)>{{ $option }} por página</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill">Aplicar filtros</button>
                <a href="{{ route('admin.customers.files.subscribers', ['id' => $fileRouteKey]) }}" class="btn btn-outline-secondary btn-sm rounded-pill">Limpar</a>
            </form>
        </div>
    </div>

    <div class="customer-context-banner">
        Você está visualizando os assinantes deste cliente em modo administrativo.
        Escopo travado por lista <strong title="ID interno: #{{ (int) $file->id }}">{{ $listDisplayName }}</strong> e cliente <strong>{{ $customerDisplayName }}</strong>,
        sem trocar sua sessão para o cliente.
    </div>

    <div class="subscribers-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small text-muted">
            <strong>Lista:</strong> <span title="ID interno: #{{ (int) $file->id }} | List ID: {{ $fileRouteKey }}">{{ $listDisplayName }}</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.customers.files.show', ['id' => $fileRouteKey]) }}" class="btn btn-outline-secondary btn-sm rounded-pill">Detalhes</a>
            <a href="{{ route('admin.customers.files.index', ['tenant_uuid' => $isGlobalSuper ? $tenantUuid : null]) }}" class="btn btn-outline-secondary btn-sm rounded-pill">Listas de arquivos</a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm admin-tenant-users-metric">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-tenant-users-metric-box">
                        <span class="grade-profile-field-kicker">Assinantes</span>
                        <div class="admin-tenant-users-metric-value">{{ $totalSubscribers }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm admin-tenant-users-metric">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-tenant-users-metric-box">
                        <span class="grade-profile-field-kicker">Com e-mail</span>
                        <div class="admin-tenant-users-metric-value">{{ $withEmail }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm admin-tenant-users-metric">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-tenant-users-metric-box">
                        <span class="grade-profile-field-kicker">Com telefone</span>
                        <div class="admin-tenant-users-metric-value">{{ $withPhone }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm admin-tenant-users-metric">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-tenant-users-metric-box">
                        <span class="grade-profile-field-kicker">Score médio</span>
                        <div class="admin-tenant-users-metric-value">{{ $avgScore }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm admin-tenant-users-table-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <strong>Assinantes</strong>
                <div id="subscribersBulkActions" class="subscribers-bulk-toolbar d-none" aria-live="polite">
                    <span class="subscribers-bulk-count" id="subscribersSelectedCount">0 selecionados</span>
                    <label class="subscribers-bulk-toggle" for="subscribersSelectAllVisible">
                        <input class="form-check-input m-0" type="checkbox" id="subscribersSelectAllVisible" title="Selecionar todos da página">
                        Selecionar todos da página
                    </label>
                    <select id="subscribersBulkAction" class="form-select form-select-sm">
                        <option value="">Escolha uma ação</option>
                        <option value="subscribe">Inscrever</option>
                        <option value="unsubscribe">Desinscrever</option>
                        <option value="unconfirm">Desconfirmar</option>
                        <option value="resend_confirmation">Reenviar e-mail de confirmação</option>
                        <option value="deactivate">Desativar</option>
                        <option value="delete">Excluir</option>
                        <option value="blocked_ips">IPs bloqueados</option>
                    </select>
                    <select id="subscribersBulkScope" class="form-select form-select-sm subscribers-bulk-scope">
                        <option value="selected">Aplicar nos selecionados</option>
                        <option value="all_filtered">Aplicar aos filtrados</option>
                    </select>
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="subscribersBulkApply" disabled>Aplicar</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="subscribersBulkClear">Limpar</button>
                    <div class="subscribers-bulk-status d-none" id="subscribersBulkInlineStatus"></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="{{ route('admin.customers.files.subscribers', ['id' => $fileRouteKey]) }}" class="d-flex align-items-center gap-2">
                    @if($isGlobalSuper)
                        <input type="hidden" name="tenant_uuid" value="{{ $tenantUuid }}">
                    @endif
                    <input type="hidden" name="q" value="{{ $search }}">
                    <input type="hidden" name="score_min" value="{{ $scoreMin }}">
                    <label for="subscribersPerPage" class="small text-muted mb-0">Exibir</label>
                    <select id="subscribersPerPage" name="per_page" class="form-select form-select-sm" style="width:115px;" onchange="this.form.submit()">
                        @foreach($perPageAllowed as $option)
                            <option value="{{ $option }}" @selected((int) $perPage === (int) $option)>{{ $option }}/pág</option>
                        @endforeach
                    </select>
                </form>
                <span class="badge bg-light text-dark border rounded-pill">{{ $subscribers->total() }}</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 admin-tenant-users-table admin-enterprise-table">
                <thead class="table-light">
                <tr>
                    <th class="subscribers-select-col">
                        <input class="form-check-input m-0" type="checkbox" id="subscribersSelectAllPage" title="Selecionar todos da página">
                    </th>
                    <th data-col="name">Nome</th>
                    <th data-col="cpf">CPF</th>
                    <th data-col="email">E-mail</th>
                    <th data-col="phone">Telefone</th>
                    <th data-col="birth_date">Data de nascimento</th>
                    <th data-col="sex">Sexo</th>
                    <th data-col="score" style="width:90px">Score</th>
                    <th data-col="whatsapp">WhatsApp</th>
                    <th data-col="cityuf">Cidade/UF</th>
                    <th data-col="entity">Tipo</th>
                    <th data-col="lifecycle">Estágio</th>
                    <th data-col="consent">Consentimento</th>
                    @foreach($dynamicExtraColumns as $dynamicCol)
                        <th data-col="{{ $dynamicCol['col'] }}" class="d-none">{{ $dynamicCol['label'] }}</th>
                    @endforeach
                    <th data-col="created" style="width:150px">Adicionado em</th>
                    <th data-col="updated" style="width:150px">Atualizado em</th>
                    <th class="text-end" style="width:88px">Ações</th>
                </tr>
                </thead>
                <tbody>
                @forelse($subscribers as $subscriber)
                    @php
                        $editQueryParams = array_filter([
                            'q' => $search !== '' ? $search : null,
                            'score_min' => $scoreMin !== '' ? $scoreMin : null,
                            'per_page' => (int) $perPage !== 20 ? (int) $perPage : null,
                            'page' => (int) $subscribers->currentPage() > 1 ? (int) $subscribers->currentPage() : null,
                        ], static fn ($v): bool => $v !== null && $v !== '');
                        $subscriberEditUrl = route('admin.customers.files.subscribers.edit', array_merge([
                            'id' => $fileRouteKey,
                            'subscriberId' => ($subscriber->public_uid ?: $subscriber->id),
                        ], $editQueryParams));
                    @endphp
                    <tr data-enterprise-row="1">
                        @php
                            $standard = is_array($subscriber->standard_columns ?? null) ? $subscriber->standard_columns : [];
                            $formattedStandard = is_array($subscriber->standard_columns_formatted ?? null) ? $subscriber->standard_columns_formatted : [];
                            $rowName = trim((string) ($standard['nome'] ?? $subscriber->name ?? ''));
                            $rowCpf = trim((string) ($standard['cpf'] ?? $subscriber->cpf ?? ''));
                            $rowCpfFormatted = trim((string) ($formattedStandard['cpf'] ?? $rowCpf));
                            $rowEmail = trim((string) ($standard['email'] ?? $subscriber->email ?? ''));
                            $rowPhone = trim((string) ($standard['phone'] ?? $subscriber->phone_e164 ?? ''));
                            $rowPhoneFormatted = trim((string) ($formattedStandard['phone'] ?? $rowPhone));
                            $rowBirthDate = trim((string) ($standard['data_nascimento'] ?? ''));
                            $rowBirthDateFormatted = $formatBirthDate($rowBirthDate);
                            $rowSex = trim((string) ($standard['sex'] ?? $subscriber->sex ?? ''));
                            $rowSexFormatted = trim((string) ($formattedStandard['sex'] ?? $rowSex));
                            $rowScore = (int) ($standard['score'] ?? $subscriber->score ?? 0);
                            $rowWhatsapp = trim((string) ($subscriber->whatsapp_e164 ?? ''));
                            $rowWhatsappFormatted = $formatBrPhone($rowWhatsapp);
                            $rowExtras = is_array($subscriber->filtered_extras_json ?? null)
                                ? $subscriber->filtered_extras_json
                                : (is_array($subscriber->extras_json ?? null) ? $subscriber->extras_json : []);
                        @endphp
                        <td class="subscribers-select-col">
                            <input
                                class="form-check-input m-0 js-subscriber-row-select"
                                type="checkbox"
                                value="{{ (string) ($subscriber->public_uid ?: $subscriber->id) }}"
                                data-subscriber-id="{{ (int) $subscriber->id }}"
                            >
                        </td>
                        <td data-col="name">
                            <a href="{{ $subscriberEditUrl }}" class="fw-semibold text-decoration-none">{{ $rowName !== '' ? $rowName : 'Sem nome' }}</a>
                        </td>
                        <td data-col="cpf">{{ $rowCpfFormatted !== '' ? $rowCpfFormatted : '—' }}</td>
                        <td data-col="email">{{ $rowEmail !== '' ? $rowEmail : '—' }}</td>
                        <td data-col="phone">{{ $rowPhoneFormatted !== '' ? $rowPhoneFormatted : '—' }}</td>
                        <td data-col="birth_date">{{ $rowBirthDateFormatted !== '' ? $rowBirthDateFormatted : '—' }}</td>
                        <td data-col="sex">{{ $rowSexFormatted !== '' ? $rowSexFormatted : '—' }}</td>
                        <td data-col="score"><span class="badge bg-light text-dark border rounded-pill">{{ $rowScore }}</span></td>
                        <td data-col="whatsapp">{{ $rowWhatsappFormatted !== '' ? $rowWhatsappFormatted : '—' }}</td>
                        <td data-col="cityuf">{{ trim((string) (($subscriber->city ?: '') . (($subscriber->uf ? '/' . $subscriber->uf : '')))) ?: '—' }}</td>
                        <td data-col="entity">{{ $subscriber->entity_type ?: '—' }}</td>
                        <td data-col="lifecycle">{{ $subscriber->lifecycle_stage ?: '—' }}</td>
                        <td data-col="consent">
                            @php
                                $consents = [];
                                if ((bool) ($subscriber->optin_email ?? false)) { $consents[] = 'Email'; }
                                if ((bool) ($subscriber->optin_sms ?? false)) { $consents[] = 'SMS'; }
                                if ((bool) ($subscriber->optin_whatsapp ?? false)) { $consents[] = 'WhatsApp'; }
                                $consentLabel = $consents !== [] ? implode(', ', $consents) : '—';
                            @endphp
                            <span class="small">{{ $consentLabel }}</span>
                        </td>
                        @php
                            $extras = $rowExtras;
                        @endphp
                        @foreach($dynamicExtraColumns as $dynamicCol)
                            @php
                                $extraRaw = $extras[$dynamicCol['key']] ?? null;
                                if (is_array($extraRaw)) {
                                    $extraValue = json_encode($extraRaw, JSON_UNESCAPED_UNICODE);
                                } else {
                                    $extraValue = trim((string) ($extraRaw ?? ''));
                                }
                            @endphp
                            <td data-col="{{ $dynamicCol['col'] }}" class="d-none">{{ $extraValue !== '' ? $extraValue : '—' }}</td>
                        @endforeach
                        <td data-col="created">{{ optional($subscriber->created_at)->format('d/m/Y H:i') ?: '—' }}</td>
                        <td data-col="updated">{{ optional($subscriber->updated_at)->format('d/m/Y H:i') ?: '—' }}</td>
                        <td class="text-end">
                            <div class="dropdown admin-table-actions-dropdown">
                                <button class="btn btn-sm admin-table-actions-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir ações">
                                    <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end admin-table-actions-menu">
                                    <li>
                                        <button
                                            type="button"
                                            class="dropdown-item"
                                            data-subscriber-profile="1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#subscriberProfileModal"
                                            data-subscriber-id="{{ (int) $subscriber->id }}"
                                            data-subscriber-row="{{ (int) ($subscriber->row_num ?? 0) }}"
                                            data-subscriber-name="{{ $rowName !== '' ? $rowName : '' }}"
                                            data-subscriber-email="{{ $rowEmail !== '' ? $rowEmail : '' }}"
                                            data-subscriber-cpf="{{ $rowCpfFormatted !== '' ? $rowCpfFormatted : '' }}"
                                            data-subscriber-phone="{{ $rowPhoneFormatted !== '' ? $rowPhoneFormatted : '' }}"
                                            data-subscriber-birth-date="{{ $rowBirthDateFormatted !== '' ? $rowBirthDateFormatted : '' }}"
                                            data-subscriber-whatsapp="{{ $rowWhatsappFormatted !== '' ? $rowWhatsappFormatted : '' }}"
                                            data-subscriber-city="{{ $subscriber->city ?: '' }}"
                                            data-subscriber-uf="{{ $subscriber->uf ?: '' }}"
                                            data-subscriber-score="{{ $rowScore }}"
                                            data-subscriber-entity="{{ $subscriber->entity_type ?: '' }}"
                                            data-subscriber-lifecycle="{{ $subscriber->lifecycle_stage ?: '' }}"
                                            data-subscriber-sex="{{ $rowSexFormatted !== '' ? $rowSexFormatted : '' }}"
                                            data-subscriber-created="{{ optional($subscriber->created_at)->format('d/m/Y H:i') ?: '' }}"
                                            data-subscriber-updated="{{ optional($subscriber->updated_at)->format('d/m/Y H:i') ?: '' }}"
                                            data-subscriber-extras='@json($rowExtras)'
                                            data-subscriber-consent-email="{{ (bool) ($subscriber->optin_email ?? false) ? '1' : '0' }}"
                                            data-subscriber-consent-sms="{{ (bool) ($subscriber->optin_sms ?? false) ? '1' : '0' }}"
                                            data-subscriber-consent-whatsapp="{{ (bool) ($subscriber->optin_whatsapp ?? false) ? '1' : '0' }}"
                                            data-subscriber-consent-source="{{ $subscriber->consent_source ?: '' }}"
                                            data-subscriber-consent-at="{{ optional($subscriber->consent_at)->format('d/m/Y H:i') ?: '' }}"
                                            data-subscriber-segment-id="{{ (string) ($subscriber->segment_id ?? '') }}"
                                            data-subscriber-niche-id="{{ (string) ($subscriber->niche_id ?? '') }}"
                                            data-subscriber-origin-id="{{ (string) ($subscriber->origin_id ?? '') }}"
                                            data-subscriber-last-interaction="{{ optional($subscriber->last_interaction_at)->format('d/m/Y H:i') ?: '' }}"
                                            data-subscriber-next-action="{{ optional($subscriber->next_action_at)->format('d/m/Y H:i') ?: '' }}"
                                            data-subscriber-edit-url="{{ $subscriberEditUrl }}"
                                        >
                                            Perfil do assinante
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 16 + count($dynamicExtraColumns) }}" class="text-center py-4">
                            <div class="admin-tenant-users-empty">
                                <div class="admin-tenant-users-empty-title">Nenhum assinante encontrado</div>
                                <div class="small text-muted">A lista será preenchida conforme normalização de registros deste arquivo.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $subscribers->links() }}
        </div>
    </div>
</div>

<div class="modal fade" id="subscriberProfileModal" tabindex="-1" aria-labelledby="subscriberProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subscriberProfileModalLabel">Perfil do assinante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="subscriber-profile-top mb-3 d-flex align-items-center justify-content-between gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <div class="subscriber-profile-avatar" id="subscriberProfileAvatar">—</div>
                        <div>
                            <div class="fw-semibold" id="subscriberProfileName">—</div>
                            <div class="small text-muted" id="subscriberProfileEmail">—</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-light text-dark border rounded-pill" id="subscriberProfileStatus">Sem status</span>
                        <span class="badge bg-light text-dark border rounded-pill">Score: <span id="subscriberProfileScore">0</span></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="#" class="btn btn-outline-secondary btn-sm rounded-pill" id="subscriberProfileOpenEdit">Editar</a>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" disabled>Timeline</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" disabled>Notas</button>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-lg-6">
                        <div class="subscriber-profile-section">
                            <div class="subscriber-profile-section-title">Identidade</div>
                            <div class="subscriber-profile-grid">
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">ID</span><div id="subscriberProfileId">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Linha</span><div id="subscriberProfileRow">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">CPF</span><div id="subscriberProfileCpf">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Data de nascimento</span><div id="subscriberProfileBirthDate">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Telefone</span><div id="subscriberProfilePhone">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">WhatsApp</span><div id="subscriberProfileWhatsapp">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Cidade/UF</span><div id="subscriberProfileCityUf">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Sexo</span><div id="subscriberProfileSex">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Tipo</span><div id="subscriberProfileEntity">—</div></div>
                            </div>
                        </div>
                        <div class="subscriber-profile-section">
                            <div class="subscriber-profile-section-title">Semântica</div>
                            <div class="subscriber-profile-grid">
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Estágio</span><div id="subscriberProfileLifecycle">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Confiabilidade</span><div id="subscriberProfileSemanticConfidence">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Segmento ID</span><div id="subscriberProfileSegmentId">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Nicho ID</span><div id="subscriberProfileNicheId">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Origem ID</span><div id="subscriberProfileOriginId">—</div></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="subscriber-profile-section">
                            <div class="subscriber-profile-section-title">Origem e auditoria</div>
                            <div class="subscriber-profile-grid">
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Arquivo</span><div>#{{ (int) $file->id }}</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Tenant UUID</span><div>{{ $tenantUuid ?: '—' }}</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Criado em</span><div id="subscriberProfileCreated">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Atualizado em</span><div id="subscriberProfileUpdated">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Última interação</span><div id="subscriberProfileLastInteraction">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Próxima ação</span><div id="subscriberProfileNextAction">—</div></div>
                            </div>
                        </div>
                        <div class="subscriber-profile-section">
                            <div class="subscriber-profile-section-title">Consentimento</div>
                            <div class="subscriber-profile-grid">
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Canais</span><div id="subscriberProfileConsent">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Origem do consentimento</span><div id="subscriberProfileConsentSource">—</div></div>
                                <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Consentimento em</span><div id="subscriberProfileConsentAt">—</div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion mt-2" id="subscriberProfileAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="subscriberProfileExtrasHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#subscriberProfileExtrasCollapse" aria-expanded="false" aria-controls="subscriberProfileExtrasCollapse">
                                Campos extras dinâmicos
                            </button>
                        </h2>
                        <div id="subscriberProfileExtrasCollapse" class="accordion-collapse collapse" aria-labelledby="subscriberProfileExtrasHeading" data-bs-parent="#subscriberProfileAccordion">
                            <div class="accordion-body">
                                <div id="subscriberProfileExtras" class="small text-muted">Nenhum campo extra.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm" id="subscriberProfileCopyJson">Copiar JSON</button>
                <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="subscribersColumnsModal" tabindex="-1" aria-labelledby="subscribersColumnsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subscribersColumnsModalLabel">Selecionar colunas da tabela</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2">As colunas extras abaixo mudam por arquivo, com base nos campos detectados.</div>
                <div class="subscribers-columns-grid" id="subscribersColumnsMenu">
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="name" checked>
                        <span class="form-check-label">Nome</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="cpf" checked>
                        <span class="form-check-label">CPF</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="email" checked>
                        <span class="form-check-label">E-mail</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="phone" checked>
                        <span class="form-check-label">Telefone</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="birth_date" checked>
                        <span class="form-check-label">Data de nascimento</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="sex" checked>
                        <span class="form-check-label">Sexo</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="whatsapp">
                        <span class="form-check-label">WhatsApp</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="cityuf" checked>
                        <span class="form-check-label">Cidade/UF</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="entity">
                        <span class="form-check-label">Tipo</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="lifecycle">
                        <span class="form-check-label">Estágio</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="consent">
                        <span class="form-check-label">Consentimento</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="score" checked>
                        <span class="form-check-label">Score</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="created" checked>
                        <span class="form-check-label">Adicionado em</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" data-toggle-col="updated" checked>
                        <span class="form-check-label">Atualizado em</span>
                    </label>
                    @foreach($dynamicExtraColumns as $dynamicCol)
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" data-toggle-col="{{ $dynamicCol['col'] }}">
                            <span class="form-check-label">{{ $dynamicCol['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm" id="subscribersColumnsReset">Resetar padrão</button>
                <button type="button" class="btn btn-primary rounded-pill btn-sm" data-bs-dismiss="modal">Concluir</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var storageKey = 'grade_subscribers_cols_file_{{ $fileRouteKey }}';
    var menu = document.getElementById('subscribersColumnsMenu');
    if (!menu) return;

    function setColumnVisible(col, isVisible) {
        var cells = document.querySelectorAll('[data-col="' + col + '"]');
        for (var i = 0; i < cells.length; i++) {
            cells[i].classList.toggle('d-none', !isVisible);
        }
    }

    function saveState(state) {
        try {
            localStorage.setItem(storageKey, JSON.stringify(state));
        } catch (e) {}
    }

    function loadState() {
        try {
            var raw = localStorage.getItem(storageKey);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    var inputs = menu.querySelectorAll('input[data-toggle-col]');
    var state = loadState() || {};
    var defaults = {};

    for (var i = 0; i < inputs.length; i++) {
        (function (input) {
            var col = input.getAttribute('data-toggle-col');
            if (!col) return;
            defaults[col] = !!input.checked;
            if (Object.prototype.hasOwnProperty.call(state, col)) {
                input.checked = !!state[col];
            }
            setColumnVisible(col, input.checked);
            input.addEventListener('change', function () {
                state[col] = !!input.checked;
                setColumnVisible(col, input.checked);
                saveState(state);
            });
        })(inputs[i]);
    }

    var resetBtn = document.getElementById('subscribersColumnsReset');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            state = {};
            for (var i = 0; i < inputs.length; i++) {
                var col = inputs[i].getAttribute('data-toggle-col');
                if (!col) continue;
                inputs[i].checked = !!defaults[col];
                setColumnVisible(col, inputs[i].checked);
            }
            saveState(state);
        });
    }
})();

(function () {
    var storageKey = 'grade_subscribers_selection_file_{{ $fileRouteKey }}';
    var stateKey = storageKey + ':state';
    var bulkEndpoint = @json(route('admin.customers.files.subscribers.bulkAction', ['id' => $fileRouteKey]));
    var tenantUuid = @json($isGlobalSuper ? $tenantUuid : '');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('.js-subscriber-row-select'));
    if (!rowChecks.length) return;

    var selectAllPage = document.getElementById('subscribersSelectAllPage');
    var selectAllVisible = document.getElementById('subscribersSelectAllVisible');
    var bulkWrap = document.getElementById('subscribersBulkActions');
    var selectedCount = document.getElementById('subscribersSelectedCount');
    var bulkAction = document.getElementById('subscribersBulkAction');
    var bulkScope = document.getElementById('subscribersBulkScope');
    var bulkApply = document.getElementById('subscribersBulkApply');
    var bulkClear = document.getElementById('subscribersBulkClear');
    var inlineStatus = document.getElementById('subscribersBulkInlineStatus');
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var currentSignature = window.location.pathname + '::' + (window.location.search || '');

    function loadSet() {
        try {
            var stateRaw = sessionStorage.getItem(stateKey);
            if (stateRaw) {
                var state = JSON.parse(stateRaw);
                if (state && state.signature && state.signature !== currentSignature) {
                    sessionStorage.removeItem(storageKey);
                }
            }
            var raw = sessionStorage.getItem(storageKey);
            if (!raw) return {};
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function saveSet(set) {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(set || {}));
            sessionStorage.setItem(stateKey, JSON.stringify({ signature: currentSignature }));
        } catch (e) {}
    }

    var selected = loadSet();

    function selectedCountInPage() {
        var count = 0;
        for (var i = 0; i < rowChecks.length; i++) {
            if (rowChecks[i].checked) count++;
        }
        return count;
    }

    function selectedTotal() {
        var keys = Object.keys(selected || {});
        var total = 0;
        for (var i = 0; i < keys.length; i++) {
            if (selected[keys[i]]) total++;
        }
        return total;
    }

    function canApplyBulk(totalSelected) {
        if (!bulkAction || !bulkAction.value) return false;
        if (!bulkScope || bulkScope.value === 'all_filtered') return true;
        return totalSelected > 0;
    }

    function syncHeaderStates() {
        var pageCount = selectedCountInPage();
        var totalRows = rowChecks.length;
        var allChecked = totalRows > 0 && pageCount === totalRows;
        var noneChecked = pageCount === 0;

        if (selectAllPage) {
            selectAllPage.checked = allChecked;
            selectAllPage.indeterminate = !allChecked && !noneChecked;
        }
        if (selectAllVisible) {
            selectAllVisible.checked = allChecked;
            selectAllVisible.indeterminate = !allChecked && !noneChecked;
        }

        var total = selectedTotal();
        if (selectedCount) {
            selectedCount.textContent = total + ' selecionado' + (total === 1 ? '' : 's');
        }
        if (bulkWrap) {
            bulkWrap.classList.toggle('d-none', total < 1);
        }
        if (bulkApply) {
            bulkApply.disabled = !canApplyBulk(total);
        }
    }

    function applySavedToRows() {
        for (var i = 0; i < rowChecks.length; i++) {
            var key = String(rowChecks[i].value || '');
            rowChecks[i].checked = !!selected[key];
        }
        syncHeaderStates();
    }

    function renderBulkStatus(message, level) {
        if (!inlineStatus) return;
        inlineStatus.classList.remove('d-none', 'text-danger', 'text-success');
        inlineStatus.classList.add(level === 'danger' ? 'text-danger' : 'text-success');
        inlineStatus.textContent = String(message || '');
    }

    function markAllInPage(checked) {
        for (var i = 0; i < rowChecks.length; i++) {
            var key = String(rowChecks[i].value || '');
            rowChecks[i].checked = !!checked;
            if (checked) selected[key] = true;
            else delete selected[key];
        }
        saveSet(selected);
        syncHeaderStates();
    }

    for (var i = 0; i < rowChecks.length; i++) {
        (function (input) {
            input.addEventListener('change', function () {
                var key = String(input.value || '');
                if (input.checked) selected[key] = true;
                else delete selected[key];
                saveSet(selected);
                syncHeaderStates();
            });
        })(rowChecks[i]);
    }

    if (selectAllPage) {
        selectAllPage.addEventListener('change', function () {
            markAllInPage(!!selectAllPage.checked);
        });
    }
    if (selectAllVisible) {
        selectAllVisible.addEventListener('change', function () {
            markAllInPage(!!selectAllVisible.checked);
        });
    }
    if (bulkAction) {
        bulkAction.addEventListener('change', function () {
            syncHeaderStates();
        });
    }
    if (bulkScope) {
        bulkScope.addEventListener('change', function () {
            syncHeaderStates();
        });
    }
    if (bulkClear) {
        bulkClear.addEventListener('click', function () {
            selected = {};
            saveSet(selected);
            for (var i = 0; i < rowChecks.length; i++) {
                rowChecks[i].checked = false;
            }
            if (bulkAction) bulkAction.value = '';
            syncHeaderStates();
        });
    }
    if (bulkApply) {
        bulkApply.addEventListener('click', function () {
            var total = selectedTotal();
            if (!bulkAction || !bulkAction.value) return;
            var scope = bulkScope ? String(bulkScope.value || 'selected') : 'selected';
            if (scope === 'selected' && total < 1) return;
            if (!bulkEndpoint || !csrfToken) {
                renderBulkStatus('Não foi possível executar ação em massa: endpoint/CSRF ausente.', 'danger');
                return;
            }
            var selectedKeys = Object.keys(selected || {}).filter(function (key) {
                return !!selected[key];
            });
            if (scope === 'selected' && !selectedKeys.length) return;
            var scopeLabel = scope === 'all_filtered'
                ? 'todos os registros filtrados na tela atual'
                : (selectedKeys.length + ' registro(s) selecionado(s)');
            if (!window.confirm('Aplicar "' + bulkAction.options[bulkAction.selectedIndex].text + '" em ' + scopeLabel + '?')) {
                return;
            }
            if (inlineStatus) {
                inlineStatus.classList.add('d-none');
                inlineStatus.textContent = '';
            }

            var params = new URLSearchParams(window.location.search || '');
            var payload = {
                action: bulkAction.value,
                scope: scope,
                subscriber_ids: selectedKeys,
                tenant_uuid: tenantUuid || '',
                q: params.get('q') || '',
                score_min: params.get('score_min') || '',
            };
            bulkApply.disabled = true;
            fetch(bulkEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            }).then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, json: json || {} };
                });
            }).then(function (result) {
                if (!result.ok || !result.json || !result.json.ok) {
                    var errorMessage = (result.json && result.json.message) ? result.json.message : 'Falha ao executar ação em massa.';
                    renderBulkStatus(errorMessage, 'danger');
                    bulkApply.disabled = false;
                    return;
                }
                renderBulkStatus(result.json.message || 'Ação em massa executada com sucesso.', 'success');
                selected = {};
                saveSet(selected);
                setTimeout(function () { window.location.reload(); }, 450);
            }).catch(function () {
                renderBulkStatus('Erro de rede ao executar ação em massa.', 'danger');
                bulkApply.disabled = false;
            });
        });
    }

    applySavedToRows();
})();

(function () {
    var pageRoot = document.querySelector('.admin-tenant-users-page');
    var modal = document.getElementById('subscriberProfileModal');
    if (!modal) return;
    var activeTrigger = null;
    var fallbackBackdrop = null;

    function renderUiHealth(message, level) {
        if (!pageRoot || !message) return;
        var id = 'subscriberProfileHealthAlert';
        var existing = document.getElementById(id);
        if (existing && existing.parentNode) existing.parentNode.removeChild(existing);
        var alert = document.createElement('div');
        alert.id = id;
        alert.className = 'alert ' + (level === 'danger' ? 'alert-danger' : 'alert-warning') + ' mb-3';
        alert.setAttribute('role', 'alert');
        alert.textContent = message;
        pageRoot.insertBefore(alert, pageRoot.firstChild);
    }

    function hideModalFallback(el) {
        if (!el) return;
        el.classList.remove('show');
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        if (fallbackBackdrop && fallbackBackdrop.parentNode) {
            fallbackBackdrop.parentNode.removeChild(fallbackBackdrop);
            fallbackBackdrop = null;
        }
    }

    function showModal(el) {
        if (!el) return;
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(el).show();
            return;
        }
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(el).modal('show');
            return;
        }
        el.style.display = 'block';
        el.removeAttribute('aria-hidden');
        el.classList.add('show');
        document.body.classList.add('modal-open');
        if (!fallbackBackdrop) {
            fallbackBackdrop = document.createElement('div');
            fallbackBackdrop.className = 'modal-backdrop fade show';
            fallbackBackdrop.setAttribute('data-fallback-backdrop', '1');
            fallbackBackdrop.addEventListener('click', function () {
                hideModalFallback(el);
            });
            document.body.appendChild(fallbackBackdrop);
        }
        renderUiHealth('Diagnóstico: Bootstrap Modal não carregado. Aplicando fallback visual temporário.', 'warning');
    }

    function textOrDash(value) {
        var v = (value || '').toString().trim();
        return v === '' ? '—' : v;
    }

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = textOrDash(value);
    }
    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    function formatBirthDate(value) {
        var raw = (value || '').toString().trim();
        if (!raw) return '';
        var m = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (m) return m[3] + '/' + m[2] + '/' + m[1];
        return raw;
    }
    function formatBrPhone(value) {
        var raw = (value || '').toString().trim();
        if (!raw) return '';
        var digits = raw.replace(/\D+/g, '');
        if (!digits) return raw;
        if (digits.indexOf('0055') === 0) digits = digits.slice(4);
        else if (digits.indexOf('055') === 0) digits = digits.slice(3);
        else if (digits.indexOf('55') === 0 && digits.length > 11) digits = digits.slice(2);
        if (digits.length < 10) return raw;
        var ddd = digits.slice(0, 2);
        var number = digits.slice(2);
        if (number.length === 9) return '+55 (' + ddd + ') ' + number.slice(0, 5) + '-' + number.slice(5);
        if (number.length === 8) return '+55 (' + ddd + ') ' + number.slice(0, 4) + '-' + number.slice(4);
        return '+55 (' + ddd + ') ' + number;
    }
    function renderExtras(extras) {
        var el = document.getElementById('subscriberProfileExtras');
        if (!el) return;
        var keys = Object.keys(extras || {});
        var html = [];
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            var value = extras[key];
            if (value === null || typeof value === 'undefined') continue;
            if (Array.isArray(value) || typeof value === 'object') {
                try { value = JSON.stringify(value); } catch (e) { value = String(value); }
            }
            var text = String(value || '').trim();
            if (!text) continue;
            html.push(
                '<div class="subscriber-profile-extras-item">' +
                    '<div class="k">' + escapeHtml(key) + '</div>' +
                    '<div class="v">' + escapeHtml(text) + '</div>' +
                '</div>'
            );
        }
        if (!html.length) {
            el.className = 'small text-muted';
            el.textContent = 'Nenhum campo extra.';
            return;
        }
        el.className = 'subscriber-profile-extras-list';
        el.innerHTML = html.join('');
    }

    function populateFromTrigger(trigger) {
        if (!trigger) return;
        activeTrigger = trigger;
        var editUrl = trigger.getAttribute('data-subscriber-edit-url') || '#';
        var editLink = document.getElementById('subscriberProfileOpenEdit');
        if (editLink) {
            editLink.setAttribute('href', editUrl);
        }

        setText('subscriberProfileId', trigger.getAttribute('data-subscriber-id'));
        setText('subscriberProfileRow', trigger.getAttribute('data-subscriber-row'));
        setText('subscriberProfileName', trigger.getAttribute('data-subscriber-name'));
        setText('subscriberProfileEmail', trigger.getAttribute('data-subscriber-email'));
        setText('subscriberProfileCpf', trigger.getAttribute('data-subscriber-cpf'));
        setText('subscriberProfileBirthDate', formatBirthDate(trigger.getAttribute('data-subscriber-birth-date')));
        setText('subscriberProfilePhone', formatBrPhone(trigger.getAttribute('data-subscriber-phone')));
        setText('subscriberProfileWhatsapp', formatBrPhone(trigger.getAttribute('data-subscriber-whatsapp')));
        setText('subscriberProfileSex', trigger.getAttribute('data-subscriber-sex'));

        var city = (trigger.getAttribute('data-subscriber-city') || '').trim();
        var uf = (trigger.getAttribute('data-subscriber-uf') || '').trim();
        var cityUf = city && uf ? (city + '/' + uf) : (city || uf || '—');
        setText('subscriberProfileCityUf', cityUf);

        setText('subscriberProfileScore', trigger.getAttribute('data-subscriber-score'));
        setText('subscriberProfileEntity', trigger.getAttribute('data-subscriber-entity'));
        setText('subscriberProfileLifecycle', trigger.getAttribute('data-subscriber-lifecycle'));
        setText('subscriberProfileCreated', trigger.getAttribute('data-subscriber-created'));
        setText('subscriberProfileUpdated', trigger.getAttribute('data-subscriber-updated'));
        setText('subscriberProfileSegmentId', trigger.getAttribute('data-subscriber-segment-id'));
        setText('subscriberProfileNicheId', trigger.getAttribute('data-subscriber-niche-id'));
        setText('subscriberProfileOriginId', trigger.getAttribute('data-subscriber-origin-id'));
        setText('subscriberProfileLastInteraction', trigger.getAttribute('data-subscriber-last-interaction'));
        setText('subscriberProfileNextAction', trigger.getAttribute('data-subscriber-next-action'));
        setText('subscriberProfileConsentSource', trigger.getAttribute('data-subscriber-consent-source'));
        setText('subscriberProfileConsentAt', trigger.getAttribute('data-subscriber-consent-at'));

        var consents = [];
        if (trigger.getAttribute('data-subscriber-consent-email') === '1') consents.push('Email');
        if (trigger.getAttribute('data-subscriber-consent-sms') === '1') consents.push('SMS');
        if (trigger.getAttribute('data-subscriber-consent-whatsapp') === '1') consents.push('WhatsApp');
        setText('subscriberProfileConsent', consents.length ? consents.join(', ') : '—');

        var lifecycle = (trigger.getAttribute('data-subscriber-lifecycle') || '').trim();
        var statusEl = document.getElementById('subscriberProfileStatus');
        if (statusEl) {
            statusEl.textContent = lifecycle !== '' ? lifecycle : 'Sem status';
            statusEl.className = 'badge rounded-pill border';
            if (lifecycle !== '' && lifecycle.toLowerCase() === 'active') {
                statusEl.classList.add('bg-success-subtle', 'text-success-emphasis');
            } else {
                statusEl.classList.add('bg-light', 'text-dark');
            }
        }
        var scoreValue = parseInt(trigger.getAttribute('data-subscriber-score') || '0', 10);
        setText('subscriberProfileSemanticConfidence', isNaN(scoreValue) ? '—' : (scoreValue + '/100'));

        var nameValue = (trigger.getAttribute('data-subscriber-name') || '').trim();
        var avatarEl = document.getElementById('subscriberProfileAvatar');
        if (avatarEl) {
            avatarEl.textContent = nameValue !== '' ? nameValue.slice(0, 2).toUpperCase() : '—';
        }

        var extrasRaw = trigger.getAttribute('data-subscriber-extras') || '{}';
        var extras;
        try {
            extras = JSON.parse(extrasRaw);
        } catch (e) {
            extras = {};
        }
        renderExtras(extras || {});

        modal.dataset.subscriberExtrasJson = JSON.stringify(extras || {});
        modal.dataset.subscriberId = trigger.getAttribute('data-subscriber-id') || '';
    }

    modal.addEventListener('show.bs.modal', function (event) {
        populateFromTrigger(event.relatedTarget);
    });

    var profileButtons = Array.prototype.slice.call(document.querySelectorAll('[data-subscriber-profile="1"]'));
    for (var i = 0; i < profileButtons.length; i++) {
        (function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                populateFromTrigger(button);
                showModal(modal);
            });
        })(profileButtons[i]);
    }

    var dismissButtons = modal.querySelectorAll('[data-bs-dismiss="modal"], .btn-close');
    for (var d = 0; d < dismissButtons.length; d++) {
        (function (button) {
            button.addEventListener('click', function () {
                if (window.bootstrap && window.bootstrap.Modal) return;
                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) return;
                hideModalFallback(modal);
            });
        })(dismissButtons[d]);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (!modal.classList.contains('show')) return;
        if (window.bootstrap && window.bootstrap.Modal) return;
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) return;
        hideModalFallback(modal);
    });

    var copyBtn = document.getElementById('subscriberProfileCopyJson');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var payload = modal.dataset.subscriberExtrasJson || '{}';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(payload).then(function () {
                    copyBtn.textContent = 'Copiado';
                    setTimeout(function () { copyBtn.textContent = 'Copiar JSON'; }, 1200);
                });
                return;
            }
            copyBtn.textContent = 'Copiar indisponível';
            setTimeout(function () { copyBtn.textContent = 'Copiar JSON'; }, 1400);
        });
    }

})();
</script>
@endsection
