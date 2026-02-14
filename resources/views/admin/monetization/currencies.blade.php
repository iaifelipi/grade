@extends('layouts.app')

@section('title', 'Admin — Moedas')
@section('page-title', 'Admin')

@section('content')
@php
    $total = $items->count();
    $active = $items->where('is_active', true)->count();
@endphp
<div class="admin-monetization-page" data-currency-edit-url-template="{{ route('admin.monetization.currencies.update', ['id' => '__ID__']) }}">
    <x-admin.page-header title="Moedas" subtitle="Catálogo de moedas e configuração padrão.">
        <x-slot:actions>
            <button class="btn btn-dark btn-sm rounded-pill" type="button" data-bs-toggle="modal" data-bs-target="#currencyCreateModal">Adicionar moeda</button>
        </x-slot:actions>
    </x-admin.page-header>
    @include('admin.monetization._tabs')
    @if(session('status'))<div class="alert alert-success mb-3">{{ session('status') }}</div>@endif

    <div class="row g-2 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Moedas</span><div class="admin-metric-field-value">{{ $total }}</div></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Ativas</span><div class="admin-metric-field-value">{{ $active }}</div></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Padrão</span><div class="admin-metric-field-value">{{ $items->where('is_default', true)->count() }}</div></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm"><div class="card-header bg-white border-0 d-flex align-items-center justify-content-between"><strong>Lista de moedas</strong><span class="badge bg-light text-dark border rounded-pill">{{ $total }} registros</span></div><div class="table-responsive"><table class="table table-sm align-middle mb-0 admin-monetization-table admin-enterprise-table"><thead class="table-light"><tr><th style="width:72px">#</th><th>Código</th><th>Nome</th><th>Símbolo</th><th>Decimais</th><th>Status</th><th style="width:88px" class="text-end">Ações</th></tr></thead><tbody>
    @forelse($items as $item)
    <tr data-enterprise-row="1">
        <td><span class="admin-table-id-pill">#{{ $item->id }}</span></td><td>{{ $item->code }}</td><td>{{ $item->name }}</td><td>{{ $item->symbol }}</td><td>{{ $item->decimal_places }}</td>
        <td><span class="badge rounded-pill admin-table-status {{ $item->is_active ? 'is-success' : 'is-muted' }}">{{ $item->is_active ? 'ativo' : 'inativo' }}</span>@if($item->is_default)<span class="badge bg-light text-dark border rounded-pill ms-1">padrão</span>@endif</td>
        <td class="text-end">
            <div class="dropdown admin-table-actions-dropdown">
                <button class="btn btn-sm admin-table-actions-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir ações"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
                <ul class="dropdown-menu dropdown-menu-end admin-table-actions-menu">
                    <li>
                        <button
                            class="dropdown-item admin-table-action-item"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#currencyCreateModal"
                            data-currency-edit="1"
                            data-id="{{ $item->id }}"
                            data-code="{{ e($item->code) }}"
                            data-name="{{ e($item->name) }}"
                            data-symbol="{{ e((string) $item->symbol) }}"
                            data-decimal-places="{{ e((string) $item->decimal_places) }}"
                            data-is-active="{{ $item->is_active ? '1' : '0' }}"
                            data-is-default="{{ $item->is_default ? '1' : '0' }}"
                        >
                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                            <span>Editar</span>
                        </button>
                    </li>
                    <li><form method="POST" action="{{ route('admin.monetization.currencies.update', $item->id) }}" class="d-flex gap-1">@csrf @method('PUT')
                        <input type="hidden" name="code" value="{{ $item->code }}"><input type="hidden" name="name" value="{{ $item->name }}"><input type="hidden" name="symbol" value="{{ $item->symbol }}"><input type="hidden" name="decimal_places" value="{{ $item->decimal_places }}"><input type="hidden" name="is_active" value="{{ $item->is_active ? 0 : 1 }}"><input type="hidden" name="is_default" value="{{ $item->is_default ? 1 : 0 }}">
                        <button class="dropdown-item admin-table-action-item" type="submit"><i class="bi bi-toggle-{{ $item->is_active ? 'off' : 'on' }}" aria-hidden="true"></i><span>{{ $item->is_active ? 'Desativar' : 'Ativar' }}</span></button>
                    </form></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="{{ route('admin.monetization.currencies.destroy', $item->id) }}" onsubmit="return confirm('Remover moeda?')">@csrf @method('DELETE')<button class="dropdown-item admin-table-action-item is-danger" type="submit"><i class="bi bi-trash3" aria-hidden="true"></i><span>Remover</span></button></form></li>
                </ul>
            </div>
        </td>
    </tr>
    @empty <tr><td colspan="7" class="text-center text-muted py-3">Sem moedas.</td></tr> @endforelse
    </tbody></table></div></div>
</div>

<div class="modal fade" id="currencyCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0"><div><h5 class="modal-title mb-1" id="currencyModalTitle">Adicionar moeda</h5><p class="grade-modal-hint mb-0" id="currencyModalHint">Cadastre uma nova moeda para cobranças.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body pt-3">
                <form method="POST" id="currencyCreateForm" action="{{ route('admin.monetization.currencies.store') }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="_method" id="currencyFormMethod" value="POST">
                    <div class="col-md-3"><input id="currencyFormCode" name="code" class="form-control form-control-sm" placeholder="BRL" required></div>
                    <div class="col-md-3"><input id="currencyFormName" name="name" class="form-control form-control-sm" placeholder="Real" required></div>
                    <div class="col-md-2"><input id="currencyFormSymbol" name="symbol" class="form-control form-control-sm" placeholder="R$"></div>
                    <div class="col-md-2"><input id="currencyFormDecimalPlaces" name="decimal_places" class="form-control form-control-sm" placeholder="2"></div>
                    <div class="col-12 d-flex align-items-center gap-3">
                        <label class="small"><input id="currencyFormActive" type="checkbox" name="is_active" value="1" checked> ativo</label>
                        <label class="small"><input id="currencyFormDefault" type="checkbox" name="is_default" value="1"> padrão</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary btn-sm" form="currencyCreateForm" id="currencyModalSubmit">Salvar moeda</button></div>
        </div>
    </div>
</div>
<script>
(() => {
    const root = document.querySelector('[data-currency-edit-url-template]');
    if (!root) return;
    const editUrlTemplate = root.getAttribute('data-currency-edit-url-template') || '';
    const form = document.getElementById('currencyCreateForm');
    const modal = document.getElementById('currencyCreateModal');
    if (!form || !modal || !editUrlTemplate) return;

    const storeUrl = @json(route('admin.monetization.currencies.store'));
    const method = document.getElementById('currencyFormMethod');
    const title = document.getElementById('currencyModalTitle');
    const hint = document.getElementById('currencyModalHint');
    const submit = document.getElementById('currencyModalSubmit');
    const code = document.getElementById('currencyFormCode');
    const name = document.getElementById('currencyFormName');
    const symbol = document.getElementById('currencyFormSymbol');
    const decimalPlaces = document.getElementById('currencyFormDecimalPlaces');
    const active = document.getElementById('currencyFormActive');
    const isDefault = document.getElementById('currencyFormDefault');

    const toCreateMode = () => {
        form.action = storeUrl;
        method.value = 'POST';
        title.textContent = 'Adicionar moeda';
        hint.textContent = 'Cadastre uma nova moeda para cobranças.';
        submit.textContent = 'Salvar moeda';
        form.reset();
        active.checked = true;
        isDefault.checked = false;
    };

    document.querySelectorAll('[data-currency-edit=\"1\"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            form.action = editUrlTemplate.replace('__ID__', encodeURIComponent(id || ''));
            method.value = 'PUT';
            title.textContent = 'Editar moeda';
            hint.textContent = 'Atualize os dados da moeda selecionada.';
            submit.textContent = 'Salvar alterações';
            code.value = btn.getAttribute('data-code') || '';
            name.value = btn.getAttribute('data-name') || '';
            symbol.value = btn.getAttribute('data-symbol') || '';
            decimalPlaces.value = btn.getAttribute('data-decimal-places') || '';
            active.checked = btn.getAttribute('data-is-active') === '1';
            isDefault.checked = btn.getAttribute('data-is-default') === '1';
        });
    });

    modal.addEventListener('hidden.bs.modal', toCreateMode);
})();
</script>
@endsection
