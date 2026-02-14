@extends('layouts.app')

@section('title','Admin — Editar Assinante')
@section('page-title','Admin')

@section('content')
@php
    $fileRouteKey = (string) ($file->public_uid ?: $file->id);
    $subscriberRouteKey = (string) ($subscriber->public_uid ?: $subscriber->id);
    $standardColumns = is_array($standardColumns ?? null) ? $standardColumns : [];
    $extras = is_array($filteredExtras ?? null)
        ? $filteredExtras
        : (is_array($subscriber->extras_json ?? null) ? $subscriber->extras_json : []);
    $dynamicGroups = [];
    foreach ($dynamicExtraColumns as $dynamicCol) {
        $key = (string) ($dynamicCol['key'] ?? '');
        $prefix = 'Outros';
        if (str_contains($key, '_')) {
            $prefix = ucfirst((string) explode('_', $key, 2)[0]);
        }
        $dynamicGroups[$prefix] ??= [];
        $dynamicGroups[$prefix][] = $dynamicCol;
    }
    ksort($dynamicGroups);

    $displayName = trim((string) ($standardColumns['nome'] ?? $subscriber->name ?? ''));
    $displayEmail = trim((string) ($standardColumns['email'] ?? $subscriber->email ?? ''));
    $formatCpf = static function (?string $value): string {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if ($digits === '') {
            return '';
        }
        $digits = substr($digits, 0, 11);
        if (strlen($digits) < 11) {
            return $digits;
        }
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?: $digits;
    };
    $formatPhone = static function (?string $value): string {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '0055')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '055')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '55') && strlen($digits) > 11) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) < 10) {
            return $digits;
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
    $formatBirth = static function (?string $value): string {
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
    $formatSex = static function (?string $value): string {
        $raw = strtoupper(trim((string) $value));
        if ($raw === '' || $raw === 'N/A' || $raw === '-') {
            return '';
        }
        if (in_array($raw, ['M', 'MASCULINO', 'MALE'], true)) {
            return 'M';
        }
        if (in_array($raw, ['F', 'FEMININO', 'FEMALE'], true)) {
            return 'F';
        }
        return $raw;
    };
    $standardColumnsUi = [
        'nome' => trim((string) ($standardColumns['nome'] ?? $subscriber->name ?? '')),
        'cpf' => $formatCpf((string) ($standardColumns['cpf'] ?? $subscriber->cpf ?? '')),
        'email' => trim((string) ($standardColumns['email'] ?? $subscriber->email ?? '')),
        'phone' => $formatPhone((string) ($standardColumns['phone'] ?? $subscriber->phone_e164 ?? '')),
        'data_nascimento' => $formatBirth((string) ($standardColumns['data_nascimento'] ?? '')),
        'sex' => $formatSex((string) ($standardColumns['sex'] ?? $subscriber->sex ?? '')),
        'score' => (int) ($standardColumns['score'] ?? $subscriber->score ?? 0),
    ];
    $displayInitials = collect(preg_split('/\s+/', $displayName) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr((string) $part, 0, 1)))
        ->implode('');
    if ($displayInitials === '') {
        $displayInitials = mb_strtoupper(mb_substr($displayName ?: 'S', 0, 2));
    }
@endphp

<div class="admin-tenant-users-page subscriber-edit-page">
    <style>
        .subscriber-edit-layout {
            display: block;
        }
        .subscriber-edit-main {
            border: 1px solid #eadcc8;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(97, 62, 24, .08);
        }
        .subscriber-edit-avatar {
            width: 52px;
            height: 52px;
            border-radius: 999px;
            border: 1px solid #d8c7aa;
            background: #f6efe4;
            color: #7b5e32;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .subscriber-edit-chips {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .subscriber-edit-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid #e4d2b8;
            background: #fffaf3;
            color: #7b5e32;
            border-radius: 999px;
            padding: 2px 10px;
            font-size: 12px;
            font-weight: 700;
        }
        .subscriber-edit-main-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            padding: 12px;
            border-bottom: 1px solid #f1e7d7;
            background: linear-gradient(180deg, #fff 0%, #fffaf3 100%);
        }
        .subscriber-edit-main-title {
            font-size: 14px;
            font-weight: 800;
            color: #5b4020;
        }
        .subscriber-edit-main-sub {
            color: #6c4f2a;
            font-size: 12px;
            font-weight: 600;
        }
        .subscriber-edit-head-identity {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex: 1 1 640px;
            flex-wrap: wrap;
        }
        .subscriber-edit-head-meta {
            min-width: 0;
        }
        .subscriber-edit-head-name {
            font-size: 14px;
            font-weight: 700;
            color: #4f381d;
            line-height: 1.2;
            margin-bottom: 2px;
            word-break: break-word;
        }
        .subscriber-edit-dirty-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border: 1px solid #d8c7aa;
            border-radius: 999px;
            background: #f6efe4;
            color: #7b5e32;
            font-size: 12px;
            font-weight: 700;
        }

        .subscriber-edit-form-wrap {
            padding: 12px;
        }
        .subscriber-edit-tabs {
            border-bottom: 1px dashed #e8d9c3;
            margin-bottom: 12px;
            padding-bottom: 12px;
        }
        .subscriber-edit-tab-btn {
            border: 1px solid #dec8a8;
            background: #fffaf3;
            color: #6f4f29;
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 700;
        }
        .subscriber-edit-tab-btn.active {
            border-color: #7d5a2d;
            background: #1f2a44;
            color: #fff;
        }

        .subscriber-edit-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 12px;
        }
        .subscriber-edit-dynamic-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 12px;
        }
        .subscriber-edit-section {
            border: 1px solid #eadcc8;
            border-radius: 12px;
            background: #fff;
            padding: 10px;
        }
        .subscriber-edit-section-title {
            font-size: 12px;
            color: #6a4e29;
            text-transform: uppercase;
            letter-spacing: .03em;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .subscriber-edit-field-box {
            border: 1px solid #e4d2b8;
            border-radius: 10px;
            background: #fffdf9;
            padding: 6px 9px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            transition: .16s ease;
        }
        .subscriber-edit-field-box:focus-within {
            border-color: #ae8a57;
            box-shadow: 0 0 0 3px rgba(174, 138, 87, .12);
            background: #fff;
        }
        .subscriber-edit-field-kicker {
            font-size: 11px;
            color: #6d522e;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .subscriber-edit-field-input {
            border: 0;
            outline: 0;
            background: transparent;
            width: 100%;
            font-size: 14px;
            color: #2f1f0e;
            font-weight: 600;
            padding: 0;
            box-shadow: none !important;
        }
        .subscriber-edit-field-input::placeholder {
            color: #8f7148;
        }
        .subscriber-edit-field-box.is-dirty {
            border-color: #7fbd9a;
            background: #f3fdf7;
        }

        .subscriber-consent-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .subscriber-consent-card {
            border: 1px solid #e4d2b8;
            border-radius: 12px;
            background: #fffdf9;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .subscriber-consent-card-title {
            font-size: 13px;
            font-weight: 700;
            color: #493115;
        }

        .subscriber-edit-toolbar {
            border: 1px solid #eadcc8;
            border-radius: 10px;
            background: #fffaf3;
            padding: 10px;
        }
        .subscriber-edit-toolbar .form-control {
            border-radius: 10px;
            border-color: #d6c1a0;
            color: #3e2a13;
        }
        .subscriber-edit-toolbar .btn {
            border-radius: 999px;
        }
        .subscriber-edit-toolbar .form-check-label {
            color: #5f4521;
            font-weight: 600;
        }
        #dynamicFieldsAccordion .accordion-button {
            color: #4f3619;
            font-weight: 700;
            background: #fffaf2;
        }
        #dynamicFieldsAccordion .accordion-button:not(.collapsed) {
            background: #fff5e8;
            color: #3e2b12;
            box-shadow: none;
        }
        #dynamicFieldsAccordion .accordion-body {
            background: #fff;
        }

        .subscriber-edit-sticky-actions {
            position: sticky;
            bottom: 8px;
            z-index: 30;
            border: 1px solid #d9c39f;
            border-radius: 12px;
            background: rgba(255, 250, 241, .98);
            box-shadow: 0 10px 26px rgba(107, 70, 31, .14);
            padding: 10px;
            margin-top: 12px;
            backdrop-filter: blur(6px);
        }
        .subscriber-shortcut-hint {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #8a6a3a;
        }
        .subscriber-edit-head-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        @media (max-width: 991.98px) {
            .subscriber-edit-main-head {
                align-items: flex-start;
                gap: 8px;
            }
            .subscriber-edit-head-identity {
                flex: 1 1 100%;
                gap: 8px;
            }
            .subscriber-edit-head-meta {
                flex: 1 1 220px;
            }
            .subscriber-edit-chips {
                width: 100%;
                margin-top: 2px;
                gap: 5px;
            }
            .subscriber-edit-head-actions {
                width: 100%;
                justify-content: flex-start;
            }
            .subscriber-edit-grid,
            .subscriber-edit-dynamic-grid,
            .subscriber-consent-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 640px) {
            .subscriber-edit-avatar {
                width: 44px;
                height: 44px;
                font-size: 12px;
            }
            .subscriber-edit-head-name {
                font-size: 13px;
            }
            .subscriber-edit-main-sub {
                font-size: 11px;
            }
            .subscriber-edit-chip {
                font-size: 11px;
                padding: 2px 8px;
            }
        }
    </style>

    <x-admin.page-header
        title="Editar assinante"
        subtitle="Edição completa do assinante no contexto do arquivo e tenant selecionados."
    >
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-light text-dark border rounded-pill" title="ID interno: #{{ (int) $file->id }}">Lista {{ $fileRouteKey }}</span>
                <span class="badge bg-light text-dark border rounded-pill">Assinante #{{ (int) $subscriber->id }}</span>
                <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm rounded-pill" id="subscriberEditBackBtn">Voltar para lista</a>
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('status'))
        <div class="alert alert-success admin-tenant-users-alert">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger admin-tenant-users-alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="subscriber-edit-layout">
        <section class="subscriber-edit-main">
            <div class="subscriber-edit-main-head">
                <div class="subscriber-edit-head-identity">
                    <div class="subscriber-edit-avatar">{{ $displayInitials }}</div>
                    <div class="subscriber-edit-head-meta">
                        <div class="subscriber-edit-head-name">{{ $displayName !== '' ? $displayName : 'Sem nome' }}</div>
                        <div class="subscriber-edit-main-sub">{{ $displayEmail !== '' ? $displayEmail : 'sem e-mail' }}</div>
                    </div>
                    <div class="subscriber-edit-chips">
                        <span class="subscriber-edit-chip">Score {{ (int) ($standardColumnsUi['score'] ?? 0) }}</span>
                        <span class="subscriber-edit-chip">{{ $subscriber->lifecycle_stage ?: 'Sem estágio' }}</span>
                        <span class="subscriber-edit-chip">{{ count($dynamicExtraColumns) }} extras</span>
                    </div>
                </div>
                <div class="subscriber-edit-head-actions">
                    <div class="subscriber-edit-dirty-pill">
                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
                        <span id="subscriberEditChangedCount">0 alterações</span>
                    </div>
                </div>
            </div>

            <div class="subscriber-edit-form-wrap">
                <form method="POST" action="{{ route('admin.customers.files.subscribers.update', ['id' => $fileRouteKey, 'subscriberId' => $subscriberRouteKey]) }}" id="subscriberEditForm">
                    @csrf
                    @method('PUT')
                    @if($isGlobalSuper)
                        <input type="hidden" name="tenant_uuid" value="{{ $tenantUuid }}">
                    @endif
                    @foreach($queryContext as $k => $v)
                        @continue($k === 'tenant_uuid')
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach

                    <ul class="nav subscriber-edit-tabs gap-2" id="subscriberEditTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="subscriber-edit-tab-btn active" id="tab-basic-btn" data-bs-toggle="pill" data-bs-target="#tab-basic" type="button" role="tab" aria-controls="tab-basic" aria-selected="true">Básico</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="subscriber-edit-tab-btn" id="tab-consent-btn" data-bs-toggle="pill" data-bs-target="#tab-consent" type="button" role="tab" aria-controls="tab-consent" aria-selected="false">Consentimento</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="subscriber-edit-tab-btn" id="tab-dynamic-btn" data-bs-toggle="pill" data-bs-target="#tab-dynamic" type="button" role="tab" aria-controls="tab-dynamic" aria-selected="false">Campos dinâmicos do arquivo</button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-basic" role="tabpanel" aria-labelledby="tab-basic-btn">
                            <div class="subscriber-edit-grid">
                                <div class="subscriber-edit-section">
                                    <div class="subscriber-edit-section-title">Identidade</div>
                                    <div class="subscriber-edit-grid">
                                        <label class="subscriber-edit-field-box">
                                            <span class="subscriber-edit-field-kicker">Nome</span>
                                            <input type="text" class="subscriber-edit-field-input subscriber-edit-track" name="name" value="{{ old('name', (string) ($standardColumnsUi['nome'] ?? '')) }}" maxlength="255" data-initial="{{ old('name', (string) ($standardColumnsUi['nome'] ?? '')) }}" placeholder="Nome do assinante">
                                        </label>
                                        <label class="subscriber-edit-field-box">
                                            <span class="subscriber-edit-field-kicker">CPF</span>
                                            <input type="text" class="subscriber-edit-field-input subscriber-edit-track" name="cpf" value="{{ old('cpf', (string) ($standardColumnsUi['cpf'] ?? '')) }}" maxlength="32" data-initial="{{ old('cpf', (string) ($standardColumnsUi['cpf'] ?? '')) }}" placeholder="000.000.000-00">
                                        </label>
                                        <label class="subscriber-edit-field-box">
                                            <span class="subscriber-edit-field-kicker">E-mail</span>
                                            <input type="text" class="subscriber-edit-field-input subscriber-edit-track" name="email" value="{{ old('email', (string) ($standardColumnsUi['email'] ?? '')) }}" maxlength="255" data-initial="{{ old('email', (string) ($standardColumnsUi['email'] ?? '')) }}" placeholder="assinante@dominio.com">
                                        </label>
                                        <label class="subscriber-edit-field-box">
                                            <span class="subscriber-edit-field-kicker">Data de nascimento</span>
                                            <input type="text" class="subscriber-edit-field-input subscriber-edit-track" name="data_nascimento" value="{{ old('data_nascimento', (string) ($standardColumnsUi['data_nascimento'] ?? '')) }}" maxlength="40" data-initial="{{ old('data_nascimento', (string) ($standardColumnsUi['data_nascimento'] ?? '')) }}" placeholder="DD/MM/AAAA">
                                        </label>
                                        <label class="subscriber-edit-field-box">
                                            <span class="subscriber-edit-field-kicker">Sexo</span>
                                            <input type="text" class="subscriber-edit-field-input subscriber-edit-track" name="sex" value="{{ old('sex', (string) ($standardColumnsUi['sex'] ?? '')) }}" maxlength="16" data-initial="{{ old('sex', (string) ($standardColumnsUi['sex'] ?? '')) }}" placeholder="F/M">
                                        </label>
                                        <label class="subscriber-edit-field-box">
                                            <span class="subscriber-edit-field-kicker">Tipo</span>
                                            <input type="text" class="subscriber-edit-field-input subscriber-edit-track" name="entity_type" value="{{ old('entity_type', (string) ($subscriber->entity_type ?? '')) }}" maxlength="32" data-initial="{{ old('entity_type', (string) ($subscriber->entity_type ?? '')) }}" placeholder="person">
                                        </label>
                                        <label class="subscriber-edit-field-box">
                                            <span class="subscriber-edit-field-kicker">Estágio</span>
                                            <input type="text" class="subscriber-edit-field-input subscriber-edit-track" name="lifecycle_stage" value="{{ old('lifecycle_stage', (string) ($subscriber->lifecycle_stage ?? '')) }}" maxlength="40" data-initial="{{ old('lifecycle_stage', (string) ($subscriber->lifecycle_stage ?? '')) }}" placeholder="active">
                                        </label>
                                    </div>
                                </div>

                                <div class="subscriber-edit-section">
                                    <div class="subscriber-edit-section-title">Contato</div>
                                    <div class="subscriber-edit-grid">
                                        <label class="subscriber-edit-field-box">
                                            <span class="subscriber-edit-field-kicker">Telefone</span>
                                            <input type="text" class="subscriber-edit-field-input subscriber-edit-track" name="phone_e164" value="{{ old('phone_e164', (string) ($standardColumnsUi['phone'] ?? '')) }}" maxlength="32" data-initial="{{ old('phone_e164', (string) ($standardColumnsUi['phone'] ?? '')) }}" placeholder="+5511999999999">
                                        </label>
                                        <label class="subscriber-edit-field-box">
                                            <span class="subscriber-edit-field-kicker">WhatsApp</span>
                                            <input type="text" class="subscriber-edit-field-input subscriber-edit-track" name="whatsapp_e164" value="{{ old('whatsapp_e164', (string) ($subscriber->whatsapp_e164 ?? '')) }}" maxlength="32" data-initial="{{ old('whatsapp_e164', (string) ($subscriber->whatsapp_e164 ?? '')) }}" placeholder="+5511999999999">
                                        </label>
                                        <label class="subscriber-edit-field-box">
                                            <span class="subscriber-edit-field-kicker">Cidade</span>
                                            <input type="text" class="subscriber-edit-field-input subscriber-edit-track" name="city" value="{{ old('city', (string) ($subscriber->city ?? '')) }}" maxlength="120" data-initial="{{ old('city', (string) ($subscriber->city ?? '')) }}" placeholder="São Paulo">
                                        </label>
                                        <label class="subscriber-edit-field-box">
                                            <span class="subscriber-edit-field-kicker">UF</span>
                                            <input type="text" class="subscriber-edit-field-input subscriber-edit-track" name="uf" value="{{ old('uf', (string) ($subscriber->uf ?? '')) }}" maxlength="4" data-initial="{{ old('uf', (string) ($subscriber->uf ?? '')) }}" placeholder="SP">
                                        </label>

                                        <div class="subscriber-edit-field-box" style="grid-column: 1 / -1;">
                                            <span class="subscriber-edit-field-kicker">Score (0-100)</span>
                                            <div class="d-flex align-items-center gap-2">
                                                <input type="range" class="form-range subscriber-edit-track" min="0" max="100" value="{{ old('score', (int) ($standardColumnsUi['score'] ?? 0)) }}" id="subscriberScoreRange" data-initial="{{ old('score', (int) ($standardColumnsUi['score'] ?? 0)) }}">
                                                <input type="number" class="form-control form-control-sm subscriber-edit-track" name="score" id="subscriberScoreInput" value="{{ old('score', (int) ($standardColumnsUi['score'] ?? 0)) }}" min="0" max="100" style="max-width:90px;" data-initial="{{ old('score', (int) ($standardColumnsUi['score'] ?? 0)) }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-consent" role="tabpanel" aria-labelledby="tab-consent-btn">
                            <div class="subscriber-edit-section">
                                <div class="subscriber-edit-section-title">Canais e origem</div>
                                <div class="subscriber-edit-grid">
                                    <label class="subscriber-edit-field-box" style="grid-column: 1 / -1;">
                                        <span class="subscriber-edit-field-kicker">Origem do consentimento</span>
                                        <input type="text" class="subscriber-edit-field-input subscriber-edit-track" name="consent_source" value="{{ old('consent_source', (string) ($subscriber->consent_source ?? '')) }}" maxlength="120" data-initial="{{ old('consent_source', (string) ($subscriber->consent_source ?? '')) }}" placeholder="admin.manual">
                                    </label>
                                </div>

                                <div class="subscriber-consent-grid">
                                    <div class="subscriber-consent-card">
                                        <div class="subscriber-consent-card-title">Email</div>
                                        <label class="form-check form-switch m-0">
                                            <input class="form-check-input subscriber-edit-track" type="checkbox" name="optin_email" value="1" @checked((bool) old('optin_email', (bool) ($subscriber->optin_email ?? false))) data-initial="{{ (bool) old('optin_email', (bool) ($subscriber->optin_email ?? false)) ? '1' : '0' }}">
                                        </label>
                                    </div>
                                    <div class="subscriber-consent-card">
                                        <div class="subscriber-consent-card-title">SMS</div>
                                        <label class="form-check form-switch m-0">
                                            <input class="form-check-input subscriber-edit-track" type="checkbox" name="optin_sms" value="1" @checked((bool) old('optin_sms', (bool) ($subscriber->optin_sms ?? false))) data-initial="{{ (bool) old('optin_sms', (bool) ($subscriber->optin_sms ?? false)) ? '1' : '0' }}">
                                        </label>
                                    </div>
                                    <div class="subscriber-consent-card">
                                        <div class="subscriber-consent-card-title">WhatsApp</div>
                                        <label class="form-check form-switch m-0">
                                            <input class="form-check-input subscriber-edit-track" type="checkbox" name="optin_whatsapp" value="1" @checked((bool) old('optin_whatsapp', (bool) ($subscriber->optin_whatsapp ?? false))) data-initial="{{ (bool) old('optin_whatsapp', (bool) ($subscriber->optin_whatsapp ?? false)) ? '1' : '0' }}">
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-dynamic" role="tabpanel" aria-labelledby="tab-dynamic-btn">
                            <div class="subscriber-edit-toolbar mb-2 d-flex flex-wrap align-items-center gap-2 justify-content-between">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <input type="search" id="dynamicFieldsSearch" class="form-control form-control-sm" placeholder="Buscar campo dinâmico..." style="min-width:280px;">
                                    <label class="form-check m-0">
                                        <input class="form-check-input" type="checkbox" id="dynamicOnlyFilled">
                                        <span class="form-check-label small">Mostrar apenas preenchidos</span>
                                    </label>
                                    <span class="badge bg-light text-dark border rounded-pill" id="dynamicVisibleCount">0 visíveis</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="dynamicExpandAll">Expandir grupos</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="dynamicCollapseAll">Recolher grupos</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="dynamicResetFilters">Limpar filtros</button>
                                </div>
                            </div>

                            @if(count($dynamicGroups) === 0)
                                <div class="small text-muted">Nenhum campo dinâmico detectado para este arquivo.</div>
                            @else
                                <div class="accordion" id="dynamicFieldsAccordion">
                                    @foreach($dynamicGroups as $groupName => $groupFields)
                                        <div class="accordion-item dynamic-group-item" data-group="{{ strtolower($groupName) }}">
                                            <h2 class="accordion-header" id="dynamic-group-{{ $loop->index }}">
                                                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#dynamic-group-body-{{ $loop->index }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="dynamic-group-body-{{ $loop->index }}">
                                                    {{ $groupName }} <span class="badge bg-light text-dark border rounded-pill ms-2">{{ count($groupFields) }}</span>
                                                </button>
                                            </h2>
                                            <div id="dynamic-group-body-{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="dynamic-group-{{ $loop->index }}" data-bs-parent="#dynamicFieldsAccordion">
                                                <div class="accordion-body">
                                                    <div class="subscriber-edit-dynamic-grid">
                                                        @foreach($groupFields as $dynamicCol)
                                                            @php
                                                                $fieldVal = old('extras.' . $dynamicCol['key'], (string) ($extras[$dynamicCol['key']] ?? ''));
                                                            @endphp
                                                            <label class="subscriber-edit-field-box dynamic-field-item" data-field-label="{{ strtolower($dynamicCol['label']) }}" data-field-key="{{ strtolower($dynamicCol['key']) }}">
                                                                <span class="subscriber-edit-field-kicker">{{ $dynamicCol['label'] }}</span>
                                                                <input
                                                                    type="text"
                                                                    class="subscriber-edit-field-input subscriber-edit-track dynamic-field-input"
                                                                    name="extras[{{ $dynamicCol['key'] }}]"
                                                                    value="{{ $fieldVal }}"
                                                                    data-initial="{{ $fieldVal }}"
                                                                    placeholder="—"
                                                                >
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="subscriber-edit-sticky-actions">
                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <div class="subscriber-shortcut-hint">
                                <i class="bi bi-keyboard"></i>
                                <span>Dica: use <kbd>Ctrl</kbd> + <kbd>S</kbd> para salvar rapidamente.</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm rounded-pill" id="subscriberEditCancelBtnBottom">Cancelar</a>
                                <button type="submit" class="btn btn-primary btn-sm rounded-pill" id="subscriberEditSubmitBtn">Salvar alterações</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('subscriberEditForm');
    if (!form) return;

    var submitBtn = document.getElementById('subscriberEditSubmitBtn');
    var scoreRange = document.getElementById('subscriberScoreRange');
    var scoreInput = document.getElementById('subscriberScoreInput');

    if (scoreRange && scoreInput) {
        scoreRange.addEventListener('input', function () {
            scoreInput.value = scoreRange.value;
            scoreInput.dispatchEvent(new Event('input', { bubbles: true }));
        });
        scoreInput.addEventListener('input', function () {
            var val = parseInt(scoreInput.value || '0', 10);
            if (isNaN(val)) val = 0;
            if (val < 0) val = 0;
            if (val > 100) val = 100;
            scoreInput.value = String(val);
            scoreRange.value = String(val);
        });
    }

    var changedCountEl = document.getElementById('subscriberEditChangedCount');
    var tracked = Array.prototype.slice.call(form.querySelectorAll('.subscriber-edit-track'));
    var dirty = false;

    function valueOf(el) {
        if (el.type === 'checkbox') return el.checked ? '1' : '0';
        return (el.value || '').trim();
    }

    function updateChangedCount() {
        var changed = 0;
        for (var i = 0; i < tracked.length; i++) {
            var el = tracked[i];
            var initial = (el.getAttribute('data-initial') || '').trim();
            var current = valueOf(el);
            var fieldBox = el.closest('.subscriber-edit-field-box');
            var isChanged = initial !== current;
            if (fieldBox) fieldBox.classList.toggle('is-dirty', isChanged);
            if (isChanged) changed++;
        }

        dirty = changed > 0;
        if (changedCountEl) {
            changedCountEl.textContent = changed + (changed === 1 ? ' alteração' : ' alterações');
        }
        if (submitBtn) {
            submitBtn.disabled = changed === 0;
            submitBtn.classList.toggle('opacity-75', changed === 0);
        }
    }

    for (var i = 0; i < tracked.length; i++) {
        tracked[i].addEventListener('input', updateChangedCount);
        tracked[i].addEventListener('change', updateChangedCount);
    }
    updateChangedCount();

    form.addEventListener('submit', function () {
        dirty = false;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Salvando...';
        }
    });

    window.addEventListener('beforeunload', function (event) {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    function guardLink(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', function (event) {
            if (!dirty) return;
            var ok = confirm('Você tem alterações não salvas. Deseja sair sem salvar?');
            if (!ok) event.preventDefault();
        });
    }
    guardLink('subscriberEditBackBtn');
    guardLink('subscriberEditCancelBtnBottom');

    document.addEventListener('keydown', function (event) {
        var isSave = (event.ctrlKey || event.metaKey) && String(event.key || '').toLowerCase() === 's';
        if (!isSave) return;
        event.preventDefault();
        if (submitBtn && !submitBtn.disabled) {
            submitBtn.click();
        }
    });

    var searchInput = document.getElementById('dynamicFieldsSearch');
    var onlyFilled = document.getElementById('dynamicOnlyFilled');
    var resetBtn = document.getElementById('dynamicResetFilters');
    var expandAllBtn = document.getElementById('dynamicExpandAll');
    var collapseAllBtn = document.getElementById('dynamicCollapseAll');
    var visibleCountEl = document.getElementById('dynamicVisibleCount');
    var items = Array.prototype.slice.call(document.querySelectorAll('.dynamic-field-item'));
    var groups = Array.prototype.slice.call(document.querySelectorAll('.dynamic-group-item'));
    var accordionCollapses = Array.prototype.slice.call(document.querySelectorAll('#dynamicFieldsAccordion .accordion-collapse'));

    function applyDynamicFilters() {
        var q = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
        var only = !!(onlyFilled && onlyFilled.checked);
        var visibleItems = 0;

        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var label = item.getAttribute('data-field-label') || '';
            var key = item.getAttribute('data-field-key') || '';
            var input = item.querySelector('input');
            var value = input ? (input.value || '').trim() : '';

            var matchQuery = q === '' || label.indexOf(q) !== -1 || key.indexOf(q) !== -1 || value.toLowerCase().indexOf(q) !== -1;
            var matchFilled = !only || value !== '';
            var isVisible = matchQuery && matchFilled;
            item.classList.toggle('d-none', !isVisible);
            if (isVisible) visibleItems++;
        }

        for (var g = 0; g < groups.length; g++) {
            var visible = groups[g].querySelectorAll('.dynamic-field-item:not(.d-none)').length > 0;
            groups[g].classList.toggle('d-none', !visible);
        }

        if (visibleCountEl) {
            visibleCountEl.textContent = visibleItems + ' visíveis';
        }
    }

    if (searchInput) searchInput.addEventListener('input', applyDynamicFilters);
    if (onlyFilled) onlyFilled.addEventListener('change', applyDynamicFilters);
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (onlyFilled) onlyFilled.checked = false;
            applyDynamicFilters();
        });
    }
    if (expandAllBtn && window.bootstrap && window.bootstrap.Collapse) {
        expandAllBtn.addEventListener('click', function () {
            for (var i = 0; i < accordionCollapses.length; i++) {
                window.bootstrap.Collapse.getOrCreateInstance(accordionCollapses[i], { toggle: false }).show();
            }
        });
    }
    if (collapseAllBtn && window.bootstrap && window.bootstrap.Collapse) {
        collapseAllBtn.addEventListener('click', function () {
            for (var i = 0; i < accordionCollapses.length; i++) {
                window.bootstrap.Collapse.getOrCreateInstance(accordionCollapses[i], { toggle: false }).hide();
            }
        });
    }
    applyDynamicFilters();

    var tabButtons = Array.prototype.slice.call(document.querySelectorAll('#subscriberEditTabs [data-bs-toggle="pill"]'));
    tabButtons.forEach(function (tabBtn) {
        tabBtn.addEventListener('shown.bs.tab', function () {
            tabButtons.forEach(function (tb) {
                tb.classList.toggle('active', tb === tabBtn);
            });
        });
    });
})();
</script>
@endsection
