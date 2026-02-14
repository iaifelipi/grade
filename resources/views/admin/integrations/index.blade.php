@extends('layouts.app')

@section('title','Admin — Integrações')
@section('page-title','Admin')

@section('content')
@php
    $totalIntegrations = $integrations->count();
    $activeIntegrations = $integrations->where('status', 'active')->count();
    $failedTests = $integrations->where('last_test_status', 'error')->count();
@endphp
<div class="admin-integrations-page">
    <x-admin.page-header
        title="Integrações"
        subtitle="Gerencie credenciais e configurações para ferramentas de terceiros (Mailwizz, SMS, WhatsApp e outros)."
    >
        <x-slot:actions>
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#integrationCreateModal">
                Nova integração
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('status'))
        <div class="alert alert-success mb-3">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Integrações</div>
                    <div class="fw-bold fs-5">{{ $totalIntegrations }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Ativas</div>
                    <div class="fw-bold fs-5">{{ $activeIntegrations }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Teste com falha</div>
                    <div class="fw-bold fs-5">{{ $failedTests }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div class="fw-semibold">Configurações</div>
                    <div class="text-muted small">Secrets ficam criptografados no banco; nunca mostramos valores completos na UI.</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:90px">#</th>
                                    <th>Integração</th>
                                    <th style="width:150px">Status</th>
                                    <th style="width:260px">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($integrations as $i)
                                    <tr>
                                        <td>#{{ $i->id }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $i->name }}</div>
                                            <div class="small text-muted d-flex flex-wrap gap-2">
                                                <span class="badge bg-light text-dark border">{{ $providers[$i->provider]['label'] ?? $i->provider }}</span>
                                                <span class="badge bg-light text-dark border">{{ $i->key }}</span>
                                                @if(!empty($i->last_tested_at))
                                                    <span class="badge bg-light text-dark border">últ. teste: {{ $i->last_tested_at->format('Y-m-d H:i') }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            @if($i->status === 'disabled')
                                                <span class="badge text-bg-secondary">desativada</span>
                                            @else
                                                <span class="badge text-bg-success">ativa</span>
                                            @endif
                                            @if($i->last_test_status === 'error')
                                                <div class="small text-danger mt-1">teste: falhou</div>
                                            @elseif($i->last_test_status === 'ok')
                                                <div class="small text-success mt-1">teste: ok</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                                <button
                                                    class="btn btn-sm btn-outline-secondary"
                                                    type="button"
                                                    data-admin-integration-edit="1"
                                                    data-id="{{ $i->id }}"
                                                    data-provider="{{ e((string) $i->provider) }}"
                                                    data-key="{{ e((string) $i->key) }}"
                                                    data-name="{{ e((string) $i->name) }}"
                                                    data-status="{{ e((string) $i->status) }}"
                                                    data-settings="{{ e(json_encode($i->settings_json ?? [], JSON_UNESCAPED_UNICODE)) }}"
                                                    title="Editar"
                                                >Editar</button>

                                                <form method="POST" action="{{ route('admin.integrations.test', $i->id) }}">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-primary" type="submit">Testar</button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.integrations.destroy', $i->id) }}" onsubmit="return confirm('Remover esta integração?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">Remover</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Nenhuma integração cadastrada.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <div class="fw-semibold">Eventos recentes</div>
                    <div class="text-muted small">Últimos 25 eventos (ex.: testes).</div>
                </div>
                <div class="card-body">
                    @forelse($recentEvents as $e)
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="badge {{ $e->status === 'error' ? 'text-bg-danger' : ($e->status === 'ok' ? 'text-bg-success' : 'text-bg-secondary') }}">{{ $e->status }}</span>
                            <div class="small">
                                <div class="fw-semibold">{{ $e->event_type }} @if($e->integration_id) <span class="text-muted">#{{ $e->integration_id }}</span>@endif</div>
                                <div class="text-muted">{{ $e->message ?? '—' }}</div>
                                <div class="text-muted">{{ optional($e->occurred_at)->format('Y-m-d H:i') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small">Sem eventos.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="integrationCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Nova integração</h5>
                    <div class="text-muted small">Defina provider, key estável e secrets (criptografados).</div>
                </div>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('admin.integrations.store') }}" id="integrationCreateForm" class="row g-3">
                    @csrf

                    <div class="col-md-6">
                        <label class="grade-field-box mb-0">
                            <span class="grade-field-kicker">Provider</span>
                            <select name="provider" class="grade-field-input grade-field-input--select" data-integration-provider required>
                                @foreach($providers as $k => $p)
                                    <option value="{{ $k }}">{{ $p['label'] ?? $k }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="col-md-6">
                        <label class="grade-field-box mb-0">
                            <span class="grade-field-kicker">Status</span>
                            <select name="status" class="grade-field-input grade-field-input--select">
                                <option value="active" selected>ativa</option>
                                <option value="disabled">desativada</option>
                            </select>
                        </label>
                    </div>

                    <div class="col-md-6">
                        <label class="grade-field-box mb-0">
                            <span class="grade-field-kicker">Key (estável)</span>
                            <input type="text" name="key" class="grade-field-input" maxlength="64" required placeholder="ex: mailwizz-main">
                        </label>
                        <div class="text-muted small mt-1">Usada em código/URLs. Letras/números e <code>._-</code>.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="grade-field-box mb-0">
                            <span class="grade-field-kicker">Nome</span>
                            <input type="text" name="name" class="grade-field-input" maxlength="120" required placeholder="ex: Mailwizz Principal">
                        </label>
                    </div>

                    <div class="col-12">
                        <div class="grade-modal-section">
                            <div class="fw-semibold mb-2">Secrets (criptografados)</div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="grade-field-box grade-field-box--compact mb-0">
                                        <span class="grade-field-kicker">API URL / Base URL</span>
                                        <input type="text" class="grade-field-input" name="secrets[base_url]" placeholder="https://.../api">
                                    </label>
                                    <div class="text-muted small mt-1">Mailwizz: use a API URL (pode precisar de <code>/api/index.php</code> se não tiver clean URLs).</div>
                                </div>
                                <div class="col-md-6" data-provider-only="mailwizz">
                                    <label class="grade-field-box grade-field-box--compact mb-0">
                                        <span class="grade-field-kicker">API Key</span>
                                        <input type="password" class="grade-field-input" name="secrets[api_key]" placeholder="...">
                                    </label>
                                </div>
                                <div class="col-md-6" data-provider-only="sms_gateway">
                                    <label class="grade-field-box grade-field-box--compact mb-0">
                                        <span class="grade-field-kicker">API Key</span>
                                        <input type="password" class="grade-field-input" name="secrets[api_key]" placeholder="...">
                                    </label>
                                </div>
                                <div class="col-md-6" data-provider-only="wasender">
                                    <label class="grade-field-box grade-field-box--compact mb-0">
                                        <span class="grade-field-kicker">Token</span>
                                        <input type="password" class="grade-field-input" name="secrets[token]" placeholder="...">
                                    </label>
                                </div>
                            </div>
                            <div class="text-muted small mt-2">Obs: ao editar, você precisa reenviar os secrets (não exibimos o valor atual por segurança).</div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="grade-modal-section">
                            <div class="fw-semibold mb-2">Settings (não-secret)</div>
                            <div class="row g-2">
                                <div class="col-md-6" data-provider-only="sms_gateway">
                                    <label class="grade-field-box grade-field-box--compact mb-0">
                                        <span class="grade-field-kicker">Sender ID</span>
                                        <input type="text" class="grade-field-input" name="settings[sender_id]" placeholder="Opcional">
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <label class="grade-field-box grade-field-box--compact mb-0">
                                        <span class="grade-field-kicker">Timeout (s)</span>
                                        <input type="number" class="grade-field-input" name="settings[timeout]" min="1" max="60" placeholder="Opcional">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark">Criar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="integrationEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Editar integração</h5>
                    <div class="text-muted small">Secrets não são exibidos; reenviar para substituir.</div>
                </div>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="integrationEditForm" class="row g-3">
                    @csrf
                    @method('PUT')

                    <div class="col-md-6">
                        <label class="grade-field-box mb-0">
                            <span class="grade-field-kicker">Provider</span>
                            <select name="provider" class="grade-field-input grade-field-input--select" id="integrationEditProvider" data-integration-provider required>
                                @foreach($providers as $k => $p)
                                    <option value="{{ $k }}">{{ $p['label'] ?? $k }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="col-md-6">
                        <label class="grade-field-box mb-0">
                            <span class="grade-field-kicker">Status</span>
                            <select name="status" class="grade-field-input grade-field-input--select" id="integrationEditStatus">
                                <option value="active">ativa</option>
                                <option value="disabled">desativada</option>
                            </select>
                        </label>
                    </div>

                    <div class="col-md-6">
                        <label class="grade-field-box mb-0">
                            <span class="grade-field-kicker">Key (estável)</span>
                            <input type="text" name="key" class="grade-field-input" id="integrationEditKey" maxlength="64" required>
                        </label>
                    </div>

                    <div class="col-md-6">
                        <label class="grade-field-box mb-0">
                            <span class="grade-field-kicker">Nome</span>
                            <input type="text" name="name" class="grade-field-input" id="integrationEditName" maxlength="120" required>
                        </label>
                    </div>

                    <div class="col-12">
                        <div class="grade-modal-section">
                            <div class="fw-semibold mb-2">Secrets (substitui os atuais)</div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="grade-field-box grade-field-box--compact mb-0">
                                        <span class="grade-field-kicker">API URL / Base URL</span>
                                        <input type="text" class="grade-field-input" name="secrets[base_url]" placeholder="reenviar se quiser substituir">
                                    </label>
                                    <div class="text-muted small mt-1">Mailwizz: pode precisar de <code>/api/index.php</code> se não tiver clean URLs.</div>
                                </div>
                                <div class="col-md-6" data-provider-only="mailwizz">
                                    <label class="grade-field-box grade-field-box--compact mb-0">
                                        <span class="grade-field-kicker">API Key</span>
                                        <input type="password" class="grade-field-input" name="secrets[api_key]" placeholder="reenviar se quiser substituir">
                                    </label>
                                </div>
                                <div class="col-md-6" data-provider-only="sms_gateway">
                                    <label class="grade-field-box grade-field-box--compact mb-0">
                                        <span class="grade-field-kicker">API Key</span>
                                        <input type="password" class="grade-field-input" name="secrets[api_key]" placeholder="reenviar se quiser substituir">
                                    </label>
                                </div>
                                <div class="col-md-6" data-provider-only="wasender">
                                    <label class="grade-field-box grade-field-box--compact mb-0">
                                        <span class="grade-field-kicker">Token</span>
                                        <input type="password" class="grade-field-input" name="secrets[token]" placeholder="reenviar se quiser substituir">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="grade-modal-section">
                            <div class="fw-semibold mb-2">Settings</div>
                            <div class="row g-2">
                                <div class="col-md-6" data-provider-only="sms_gateway">
                                    <label class="grade-field-box grade-field-box--compact mb-0">
                                        <span class="grade-field-kicker">Sender ID</span>
                                        <input type="text" class="grade-field-input" name="settings[sender_id]" id="integrationEditSenderId" placeholder="Opcional">
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <label class="grade-field-box grade-field-box--compact mb-0">
                                        <span class="grade-field-kicker">Timeout (s)</span>
                                        <input type="number" class="grade-field-input" name="settings[timeout]" id="integrationEditTimeout" min="1" max="60" placeholder="Opcional">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const toggleProviderFields = (provider, root) => {
        const container = root || document
        const blocks = Array.from(container.querySelectorAll('[data-provider-only]'))
        blocks.forEach((el) => {
            const only = String(el.getAttribute('data-provider-only') || '')
            el.classList.toggle('d-none', only !== provider)
        })
    }

    document.querySelectorAll('select[data-integration-provider]').forEach((sel) => {
        const form = sel.closest('form')
        const apply = () => toggleProviderFields(String(sel.value || ''), form || document)
        sel.addEventListener('change', apply)
        apply()
    })

    const editButtons = Array.from(document.querySelectorAll('[data-admin-integration-edit="1"]'))
    const editModalEl = document.getElementById('integrationEditModal')
    const editForm = document.getElementById('integrationEditForm')
    const editProvider = document.getElementById('integrationEditProvider')
    const editStatus = document.getElementById('integrationEditStatus')
    const editKey = document.getElementById('integrationEditKey')
    const editName = document.getElementById('integrationEditName')
    const editSenderId = document.getElementById('integrationEditSenderId')
    const editTimeout = document.getElementById('integrationEditTimeout')

    editButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = String(btn.getAttribute('data-id') || '').trim()
            if (!id || !editForm || !editModalEl) return
            editForm.action = `{{ route('admin.integrations.update', ['id' => '__ID__']) }}`.replace('__ID__', encodeURIComponent(id))

            const provider = String(btn.getAttribute('data-provider') || '')
            const status = String(btn.getAttribute('data-status') || 'active')
            const key = String(btn.getAttribute('data-key') || '')
            const name = String(btn.getAttribute('data-name') || '')
            let settings = {}
            try { settings = JSON.parse(String(btn.getAttribute('data-settings') || '{}')) } catch (e) {}

            if (editProvider) editProvider.value = provider
            if (editStatus) editStatus.value = status
            if (editKey) editKey.value = key
            if (editName) editName.value = name
            if (editSenderId) editSenderId.value = String(settings?.sender_id || '')
            if (editTimeout) editTimeout.value = String(settings?.timeout || '')

            toggleProviderFields(provider, editForm || document)
            window.bootstrap?.Modal?.getOrCreateInstance(editModalEl)?.show()
        })
    })
})()
</script>
@endsection
