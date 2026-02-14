@extends('layouts.app')

@section('title', 'Admin — Gateways de Pagamento')
@section('page-title', 'Admin')

@section('content')
@php
    $total = $items->count();
    $active = $items->where('is_active', true)->count();
@endphp
<div class="admin-monetization-page" data-gateway-edit-url-template="{{ route('admin.monetization.gateways.update', ['id' => '__ID__']) }}">
    <x-admin.page-header title="Gateways de Pagamento" subtitle="Cadastro e gestão dos provedores de cobrança.">
        <x-slot:actions>
            <button class="btn btn-dark btn-sm rounded-pill" type="button" data-bs-toggle="modal" data-bs-target="#gatewayCreateModal">Adicionar gateway</button>
        </x-slot:actions>
    </x-admin.page-header>
    @include('admin.monetization._tabs')

    @if(session('status'))<div class="alert alert-success mb-3">{{ session('status') }}</div>@endif

    <div class="row g-2 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Gateways</span><div class="admin-metric-field-value">{{ $total }}</div></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Ativos</span><div class="admin-metric-field-value">{{ $active }}</div></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Inativos</span><div class="admin-metric-field-value">{{ $total - $active }}</div></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
            <strong>Lista de gateways</strong>
            <span class="badge bg-light text-dark border rounded-pill">{{ $total }} registros</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 admin-monetization-table admin-enterprise-table">
                <thead class="table-light"><tr><th style="width:72px">#</th><th>Code</th><th>Nome</th><th>Provider</th><th>Taxas</th><th>Status</th><th style="width:88px" class="text-end">Ações</th></tr></thead>
                <tbody>
                @forelse($items as $item)
                    <tr data-enterprise-row="1">
                        <td><span class="admin-table-id-pill">#{{ $item->id }}</span></td>
                        <td>{{ $item->code }}</td>
                        <td>{{ $item->name }}</td>
                        <td><span class="badge bg-light text-dark border rounded-pill">{{ $item->provider }}</span></td>
                        <td>{{ $item->fee_percent }}% + {{ $item->fee_fixed_minor }}</td>
                        <td><span class="badge rounded-pill admin-table-status {{ $item->is_active ? 'is-success' : 'is-muted' }}">{{ $item->is_active ? 'ativo' : 'inativo' }}</span></td>
                        <td class="text-end">
                            <div class="dropdown admin-table-actions-dropdown">
                                <button class="btn btn-sm admin-table-actions-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir ações"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end admin-table-actions-menu">
                                    <li>
                                        <button
                                            class="dropdown-item admin-table-action-item"
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#gatewayCreateModal"
                                            data-gateway-edit="1"
                                            data-id="{{ $item->id }}"
                                            data-code="{{ e($item->code) }}"
                                            data-name="{{ e($item->name) }}"
                                            data-provider="{{ e($item->provider) }}"
                                            data-fee-percent="{{ e((string) $item->fee_percent) }}"
                                            data-fee-fixed-minor="{{ e((string) $item->fee_fixed_minor) }}"
                                            data-is-active="{{ $item->is_active ? '1' : '0' }}"
                                        >
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                            <span>Editar</span>
                                        </button>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.monetization.gateways.update', $item->id) }}">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="code" value="{{ $item->code }}">
                                            <input type="hidden" name="name" value="{{ $item->name }}">
                                            <input type="hidden" name="provider" value="{{ $item->provider }}">
                                            <input type="hidden" name="fee_percent" value="{{ $item->fee_percent }}">
                                            <input type="hidden" name="fee_fixed_minor" value="{{ $item->fee_fixed_minor }}">
                                            <input type="hidden" name="is_active" value="{{ $item->is_active ? 0 : 1 }}">
                                            <button class="dropdown-item admin-table-action-item" type="submit"><i class="bi bi-toggle-{{ $item->is_active ? 'off' : 'on' }}" aria-hidden="true"></i><span>{{ $item->is_active ? 'Desativar' : 'Ativar' }}</span></button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><form method="POST" action="{{ route('admin.monetization.gateways.destroy', $item->id) }}" onsubmit="return confirm('Remover gateway?')">@csrf @method('DELETE')<button class="dropdown-item admin-table-action-item is-danger" type="submit"><i class="bi bi-trash3" aria-hidden="true"></i><span>Remover</span></button></form></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">Sem gateways.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="gatewayCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1" id="gatewayModalTitle">Adicionar gateway</h5>
                    <p class="grade-modal-hint mb-0" id="gatewayModalHint">Cadastre um novo provedor de pagamento.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-3">
                <form method="POST" id="gatewayCreateForm" action="{{ route('admin.monetization.gateways.store') }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="_method" id="gatewayFormMethod" value="POST">
                    <div class="col-md-3"><input id="gatewayFormCode" name="code" class="form-control form-control-sm" placeholder="code" required></div>
                    <div class="col-md-3"><input id="gatewayFormName" name="name" class="form-control form-control-sm" placeholder="Nome" required></div>
                    <div class="col-md-2"><input id="gatewayFormProvider" name="provider" class="form-control form-control-sm" placeholder="Provider" required></div>
                    <div class="col-md-2"><input id="gatewayFormFeePercent" name="fee_percent" class="form-control form-control-sm" placeholder="Taxa %"></div>
                    <div class="col-md-2"><input id="gatewayFormFeeFixedMinor" name="fee_fixed_minor" class="form-control form-control-sm" placeholder="Taxa fixa minor"></div>
                    <div class="col-12 d-flex align-items-center"><label class="small"><input id="gatewayFormActive" type="checkbox" name="is_active" value="1" checked> ativo</label></div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm" form="gatewayCreateForm" id="gatewayModalSubmit">Salvar gateway</button>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const root = document.querySelector('[data-gateway-edit-url-template]');
    if (!root) return;
    const editUrlTemplate = root.getAttribute('data-gateway-edit-url-template') || '';
    const form = document.getElementById('gatewayCreateForm');
    const modal = document.getElementById('gatewayCreateModal');
    if (!form || !modal || !editUrlTemplate) return;

    const method = document.getElementById('gatewayFormMethod');
    const title = document.getElementById('gatewayModalTitle');
    const hint = document.getElementById('gatewayModalHint');
    const submit = document.getElementById('gatewayModalSubmit');
    const code = document.getElementById('gatewayFormCode');
    const name = document.getElementById('gatewayFormName');
    const provider = document.getElementById('gatewayFormProvider');
    const feePercent = document.getElementById('gatewayFormFeePercent');
    const feeFixedMinor = document.getElementById('gatewayFormFeeFixedMinor');
    const active = document.getElementById('gatewayFormActive');
    const storeUrl = @json(route('admin.monetization.gateways.store'));

    const toCreateMode = () => {
        form.action = storeUrl;
        method.value = 'POST';
        title.textContent = 'Adicionar gateway';
        hint.textContent = 'Cadastre um novo provedor de pagamento.';
        submit.textContent = 'Salvar gateway';
        form.reset();
        active.checked = true;
    };

    document.querySelectorAll('[data-gateway-edit=\"1\"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            form.action = editUrlTemplate.replace('__ID__', encodeURIComponent(id || ''));
            method.value = 'PUT';
            title.textContent = 'Editar gateway';
            hint.textContent = 'Atualize os dados do gateway selecionado.';
            submit.textContent = 'Salvar alterações';
            code.value = btn.getAttribute('data-code') || '';
            name.value = btn.getAttribute('data-name') || '';
            provider.value = btn.getAttribute('data-provider') || '';
            feePercent.value = btn.getAttribute('data-fee-percent') || '';
            feeFixedMinor.value = btn.getAttribute('data-fee-fixed-minor') || '';
            active.checked = btn.getAttribute('data-is-active') === '1';
        });
    });

    modal.addEventListener('hidden.bs.modal', toCreateMode);
})();
</script>
@endsection
