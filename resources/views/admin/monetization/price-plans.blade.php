@extends('layouts.app')

@section('title', 'Admin — Planos de Preço')
@section('page-title', 'Admin')

@section('content')
@php
    $total = $items->count();
    $active = $items->where('is_active', true)->count();
@endphp
<div class="admin-monetization-page" data-price-plan-edit-url-template="{{ route('admin.monetization.price-plans.update', ['id' => '__ID__']) }}">
    <x-admin.page-header title="Planos de Preço" subtitle="Catálogo de planos e intervalos de cobrança.">
        <x-slot:actions>
            <button class="btn btn-dark btn-sm rounded-pill" type="button" data-bs-toggle="modal" data-bs-target="#pricePlanCreateModal">Adicionar plano</button>
        </x-slot:actions>
    </x-admin.page-header>
    @include('admin.monetization._tabs')
    @if(session('status'))<div class="alert alert-success mb-3">{{ session('status') }}</div>@endif

    <div class="row g-2 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Planos</span><div class="admin-metric-field-value">{{ $total }}</div></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Ativos</span><div class="admin-metric-field-value">{{ $active }}</div></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Inativos</span><div class="admin-metric-field-value">{{ $total - $active }}</div></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between"><strong>Lista de planos</strong><span class="badge bg-light text-dark border rounded-pill">{{ $total }} registros</span></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0 admin-monetization-table admin-enterprise-table"><thead class="table-light"><tr><th style="width:72px">#</th><th>Code</th><th>Nome</th><th>Intervalo</th><th>Preço</th><th>Status</th><th style="width:88px" class="text-end">Ações</th></tr></thead><tbody>
        @forelse($items as $item)
        <tr data-enterprise-row="1">
            <td><span class="admin-table-id-pill">#{{ $item->id }}</span></td><td>{{ $item->code }}</td><td>{{ $item->name }}</td><td><span class="badge bg-light text-dark border rounded-pill">{{ $item->billing_interval }}</span></td><td>{{ $item->currency_code }} {{ number_format((int) $item->amount_minor / 100, 2, ',', '.') }}</td><td><span class="badge rounded-pill admin-table-status {{ $item->is_active ? 'is-success' : 'is-muted' }}">{{ $item->is_active ? 'ativo' : 'inativo' }}</span></td>
            <td class="text-end">
                <div class="dropdown admin-table-actions-dropdown">
                    <button class="btn btn-sm admin-table-actions-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir ações"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end admin-table-actions-menu">
                        <li>
                            <button
                                class="dropdown-item admin-table-action-item"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#pricePlanCreateModal"
                                data-price-plan-edit="1"
                                data-id="{{ $item->id }}"
                                data-code="{{ e($item->code) }}"
                                data-name="{{ e($item->name) }}"
                                data-description="{{ e((string) $item->description) }}"
                                data-billing-interval="{{ e($item->billing_interval) }}"
                                data-amount-minor="{{ e((string) $item->amount_minor) }}"
                                data-currency-code="{{ e($item->currency_code) }}"
                                data-is-active="{{ $item->is_active ? '1' : '0' }}"
                            >
                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                <span>Editar</span>
                            </button>
                        </li>
                        <li><form method="POST" action="{{ route('admin.monetization.price-plans.update', $item->id) }}">@csrf @method('PUT')
                            <input type="hidden" name="code" value="{{ $item->code }}"><input type="hidden" name="name" value="{{ $item->name }}"><input type="hidden" name="description" value="{{ $item->description }}"><input type="hidden" name="billing_interval" value="{{ $item->billing_interval }}"><input type="hidden" name="amount_minor" value="{{ $item->amount_minor }}"><input type="hidden" name="currency_code" value="{{ $item->currency_code }}"><input type="hidden" name="is_active" value="{{ $item->is_active ? 0 : 1 }}">
                            <button class="dropdown-item admin-table-action-item" type="submit"><i class="bi bi-toggle-{{ $item->is_active ? 'off' : 'on' }}" aria-hidden="true"></i><span>{{ $item->is_active ? 'Desativar' : 'Ativar' }}</span></button>
                        </form></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><form method="POST" action="{{ route('admin.monetization.price-plans.destroy', $item->id) }}" onsubmit="return confirm('Remover plano de preço?')">@csrf @method('DELETE')<button class="dropdown-item admin-table-action-item is-danger" type="submit"><i class="bi bi-trash3" aria-hidden="true"></i><span>Remover</span></button></form></li>
                    </ul>
                </div>
            </td>
        </tr>
        @empty <tr><td colspan="7" class="text-center text-muted py-3">Sem planos.</td></tr> @endforelse
        </tbody></table></div></div>
</div>

<div class="modal fade" id="pricePlanCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div><h5 class="modal-title mb-1" id="pricePlanModalTitle">Adicionar plano de preço</h5><p class="grade-modal-hint mb-0" id="pricePlanModalHint">Configure intervalo e valor do plano.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-3">
                <form method="POST" id="pricePlanCreateForm" action="{{ route('admin.monetization.price-plans.store') }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="_method" id="pricePlanFormMethod" value="POST">
                    <div class="col-md-3"><input id="pricePlanFormCode" name="code" class="form-control form-control-sm" placeholder="starter" required></div>
                    <div class="col-md-3"><input id="pricePlanFormName" name="name" class="form-control form-control-sm" placeholder="Nome" required></div>
                    <div class="col-md-2"><select id="pricePlanFormInterval" name="billing_interval" class="form-select form-select-sm" required><option value="monthly">mensal</option><option value="yearly">anual</option><option value="one_time">único</option></select></div>
                    <div class="col-md-2"><input id="pricePlanFormAmountMinor" name="amount_minor" class="form-control form-control-sm" placeholder="1990" required></div>
                    <div class="col-md-2"><input id="pricePlanFormCurrencyCode" name="currency_code" class="form-control form-control-sm" placeholder="BRL" value="BRL" required></div>
                    <div class="col-md-12"><textarea id="pricePlanFormDescription" name="description" class="form-control form-control-sm" rows="2" placeholder="Descrição"></textarea></div>
                    <div class="col-12 d-flex align-items-center"><label class="small"><input id="pricePlanFormActive" type="checkbox" name="is_active" value="1" checked> ativo</label></div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm" form="pricePlanCreateForm" id="pricePlanModalSubmit">Salvar plano</button>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const root = document.querySelector('[data-price-plan-edit-url-template]');
    if (!root) return;
    const editUrlTemplate = root.getAttribute('data-price-plan-edit-url-template') || '';
    const form = document.getElementById('pricePlanCreateForm');
    const modal = document.getElementById('pricePlanCreateModal');
    if (!form || !modal || !editUrlTemplate) return;

    const storeUrl = @json(route('admin.monetization.price-plans.store'));
    const method = document.getElementById('pricePlanFormMethod');
    const title = document.getElementById('pricePlanModalTitle');
    const hint = document.getElementById('pricePlanModalHint');
    const submit = document.getElementById('pricePlanModalSubmit');
    const code = document.getElementById('pricePlanFormCode');
    const name = document.getElementById('pricePlanFormName');
    const interval = document.getElementById('pricePlanFormInterval');
    const amountMinor = document.getElementById('pricePlanFormAmountMinor');
    const currencyCode = document.getElementById('pricePlanFormCurrencyCode');
    const description = document.getElementById('pricePlanFormDescription');
    const active = document.getElementById('pricePlanFormActive');

    const toCreateMode = () => {
        form.action = storeUrl;
        method.value = 'POST';
        title.textContent = 'Adicionar plano de preço';
        hint.textContent = 'Configure intervalo e valor do plano.';
        submit.textContent = 'Salvar plano';
        form.reset();
        active.checked = true;
        interval.value = 'monthly';
        currencyCode.value = 'BRL';
    };

    document.querySelectorAll('[data-price-plan-edit=\"1\"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            form.action = editUrlTemplate.replace('__ID__', encodeURIComponent(id || ''));
            method.value = 'PUT';
            title.textContent = 'Editar plano de preço';
            hint.textContent = 'Atualize os dados do plano selecionado.';
            submit.textContent = 'Salvar alterações';
            code.value = btn.getAttribute('data-code') || '';
            name.value = btn.getAttribute('data-name') || '';
            description.value = btn.getAttribute('data-description') || '';
            interval.value = btn.getAttribute('data-billing-interval') || 'monthly';
            amountMinor.value = btn.getAttribute('data-amount-minor') || '';
            currencyCode.value = btn.getAttribute('data-currency-code') || 'BRL';
            active.checked = btn.getAttribute('data-is-active') === '1';
        });
    });

    modal.addEventListener('hidden.bs.modal', toCreateMode);
})();
</script>
@endsection
